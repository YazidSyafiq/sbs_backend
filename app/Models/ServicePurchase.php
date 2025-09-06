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

    // /**
    // * Send email notification for PO status change
    // */
    // private function sendStatusChangeEmail(): void
    // {
    //     try {
    //         // Load relationship yang dibutuhkan untuk email
    //         $this->load(['user.branch', 'items.product']);

    //         $recipients = [];

    //         // Tentukan penerima email berdasarkan status PO
    //         switch ($this->status) {
    //             case 'Requested':
    //                 // Kirim ke Admin, Supervisor, Manager, Super Admin
    //                 $recipients = User::whereHas('roles', function ($query) {
    //                     $query->whereIn('name', ['User', 'Admin', 'Supervisor', 'Manager', 'Super Admin']);
    //                 })->get();
    //                 break;

    //             case 'Approved':
    //             case 'In Progress':
    //             case 'Cancelled':
    //                 // Kirim ke User, Admin, dan Supervisor
    //                 $recipients = User::whereHas('roles', function ($query) {
    //                     $query->whereIn('name', ['User', 'Admin', 'Supervisor']);
    //                 })->get();
    //                 break;
    //             case 'Done':
    //                 $recipients = User::whereHas('roles', function ($query) {
    //                     $query->whereIn('name', ['User', 'Admin', 'Supervisor', 'Manager', 'Super Admin']);
    //                 })->get();
    //                 break;

    //             default:
    //                 // Status lain tidak perlu notifikasi email
    //                 return;
    //         }

    //         // Filter recipients berdasarkan role dan branch
    //         if ($this->user && $this->user->branch_id) {
    //             $recipients = $recipients->filter(function ($user) {
    //                 // Admin, Supervisor, Manager, dan Super Admin bisa melihat semua branch
    //                 if ($user->hasAnyRole(['Admin', 'Supervisor', 'Manager', 'Super Admin'])) {
    //                     return true;
    //                 }

    //                 // User hanya yang melakukan request PO tersebut
    //                 if ($user->hasRole('User')) {
    //                     return $user->id === $this->user_id;
    //                 }

    //                 return false;
    //             });
    //         }

    //         // Kirim email ke setiap penerima
    //         foreach ($recipients as $recipient) {
    //             try {
    //                 Mail::to($recipient->email)->send(
    //                     new PurchaseOrderProductsNotification($this)
    //                 );

    //                 Log::info("PO status email sent", [
    //                     'po_number' => $this->po_number,
    //                     'status' => $this->status,
    //                     'recipient' => $recipient->email
    //                 ]);
    //             } catch (\Exception $e) {
    //                 Log::error("Failed to send PO status email", [
    //                     'po_number' => $this->po_number,
    //                     'status' => $this->status,
    //                     'recipient' => $recipient->email,
    //                     'error' => $e->getMessage()
    //                 ]);
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         Log::error("Failed to process PO status email", [
    //             'po_number' => $this->po_number,
    //             'status' => $this->status,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

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
}
