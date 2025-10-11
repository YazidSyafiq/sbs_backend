<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendServicePurchaseEmailJob;

class ServicePurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'name',
        'user_id',
        'order_date',
        'expected_proccess_date',
        'status',
        'total_amount',
        'notes',
        'type_po',
        'status_paid',
        'bukti_tf',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_proccess_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServicePurchaseItem::class);
    }

    /**
    * Send email notification for PO status change
    */
    private function sendStatusChangeEmail(): void
    {
        // Dispatch job untuk mengirim email
        SendServicePurchaseEmailJob::dispatch($this->id, $this->status);

        Log::info("Service PO status email job dispatched", [
            'po_number' => $this->po_number,
            'status' => $this->status
        ]);
    }

    public static function generatePoNumber(int $userId, string $orderDate): string
    {
        // Ambil user dan branch code
        $user = User::with('branch')->find($userId);
        $branchCode = $user->branch->code ?? 'HQ'; // Default HQ jika tidak ada branch

        // Format YYYYMM dari order date
        $yearMonth = Carbon::parse($orderDate)->format('Ym');

        // Loop untuk mencari nomor yang belum digunakan
        $nextNumber = 1;
        $maxAttempts = 1000; // Batasi attempt untuk menghindari infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Cari nomor urut terakhir dengan format yang sama (termasuk soft deleted)
            $lastPo = static::withTrashed() // Include soft deleted records
                ->where('po_number', 'like', "PO/SVR/{$branchCode}/{$yearMonth}/%")
                ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
                ->first();

            if ($lastPo) {
                // Extract nomor dari PO number terakhir
                $lastNumber = (int) substr($lastPo->po_number, strrpos($lastPo->po_number, '/') + 1);
                $nextNumber = $lastNumber + 1;
            }

            $poNumber = "PO/SVR/{$branchCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Cek apakah nomor sudah ada (termasuk soft deleted)
            $exists = static::withTrashed()
                ->where('po_number', $poNumber)
                ->exists();

            if (!$exists) {
                return $poNumber;
            }

            // Jika masih ada yang sama, increment dan coba lagi
            $nextNumber++;
        }

        // Fallback jika semua attempt gagal
        return "PO/SVR/{$branchCode}/{$yearMonth}/" . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total from items
    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('selling_price');
        $this->save();
    }

    public function getInvoiceUrl(): string
    {
        return URL::route('purchase-service.invoice', ['purchaseService' => $this->id]);
    }

    public function cancel(): void
    {
        $this->status = 'Cancelled';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();
    }

    public function canBeRequested(): array
    {
        $validationErrors = [];

        // Cek apakah ada items
        if ($this->items()->count() === 0) {
            $validationErrors[] = 'Purchase order must have at least one item';
        }

        // Cek validasi berdasarkan type_po
        if ($this->type_po === 'cash') {
            // Untuk cash purchase, harus sudah bayar dan ada bukti transfer
            if ($this->status_paid !== 'paid') {
                $validationErrors[] = 'Cash purchase requires payment status to be "Paid"';
            }

            if (empty($this->bukti_tf)) {
                $validationErrors[] = 'Cash purchase requires payment receipt upload';
            }
        }

        // Cek apakah name sudah diisi
        if (empty($this->name)) {
            $validationErrors[] = 'PO Name is required';
        }

        return [
            'can_request' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function request(): bool
    {
        $requestCheck = $this->canBeRequested();

        if (!$requestCheck['can_request']) {
            return false;
        }

        $this->status = 'Requested';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

    public function canBeApproved(): array
    {
        $validationErrors = [];

        // Cek apakah semua technician sudah diisi
        $itemsWithoutTechnician = $this->items()->whereNull('technician_id')->count();
        // Atau jika menggunakan field berbeda, ganti 'technician_id' dengan field yang sesuai
        // $itemsWithoutTechnician = $this->items()->where('technician_id', '')->count();

        if ($itemsWithoutTechnician > 0) {
            $validationErrors[] = 'All items must have technician assigned before approval';
        }

        return [
            'can_approve' => empty($validationErrors), // Perbaikan: seharusnya 'can_approve' bukan 'can_request'
            'validation_errors' => $validationErrors,
        ];
    }

    public function approve(): bool
    {
        $requestCheck = $this->canBeApproved();

        if (!$requestCheck['can_approve']) {
            return false;
        }

        foreach ($this->items as $item) {
            $technician = Technician::where('id', $item->technician_id)->first();

            $technician->update([
                'total_po' => $technician->total_po + $item->cost_price,
            ]);

            if ($this->type_po === 'credit') {
                $technician->update([
                    'piutang' => $technician->piutang + $item->cost_price,
                ]);
            }
        }

        $this->status = 'Approved';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

    public function canBeProgress(): array
    {
        $validationErrors = [];

        // Cek apakah name sudah diisi
        if (empty($this->expected_proccess_date)) {
            $validationErrors[] = 'Expected proccess date must be set before proccess';
        }

        return [
            'can_proccess' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function progress(): bool
    {
        $requestCheck = $this->canBeProgress();

        if (!$requestCheck['can_proccess']) {
            return false;
        }

        $this->status = 'In Progress';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

     public function canBeCompleted(): array
    {
        $validationErrors = [];

        // Cek validasi untuk credit purchase
        if ($this->type_po === 'credit') {
            // Untuk credit purchase, harus sudah bayar dan ada bukti transfer
            if ($this->status_paid !== 'paid') {
                $validationErrors[] = 'Credit purchase requires payment status to be "Paid"';
            }

            if (empty($this->bukti_tf)) {
                $validationErrors[] = 'Credit purchase requires payment receipt upload';
            }
        }

        return [
            'can_complete' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function done(): bool
    {
        $requestCheck = $this->canBeCompleted();

        if (!$requestCheck['can_complete']) {
            return false;
        }

        foreach ($this->items as $item) {
            $technician = Technician::where('id', $item->technician_id)->first();

            if ($this->type_po === 'credit') {
                $technician->update([
                    'piutang' => $technician->piutang - $item->cost_price,
                ]);
            }
        }

        $this->status = 'Done';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }
}
