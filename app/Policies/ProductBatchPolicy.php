<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProductBatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductBatchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_product::batch');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('view_product::batch');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_product::batch');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('update_product::batch');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('delete_product::batch');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_product::batch');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('force_delete_product::batch');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_product::batch');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('restore_product::batch');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_product::batch');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ProductBatch $productBatch): bool
    {
        return $user->can('replicate_product::batch');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_product::batch');
    }
}
