<?php

namespace App\Repositories;

use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SubcategoryRepositoryInterface
{
   /**
    * Get all subcategories with pagination, search and filters
    */
   public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

   /**
    * Get all subcategories
    */
   public function getAll(): Collection;

   /**
    * Get subcategory by ID
    */
   public function findById(int $id): ?Subcategory;

   /**
    * Create a new subcategory
    */
   public function create(array $data): Subcategory;

   /**
    * Update subcategory
    */
   public function update(int $id, array $data): ?Subcategory;

   /**
    * Delete subcategory
    */
   public function delete(int $id): bool;

   /**
    * Get subcategories by category ID
    */
   public function getByCategoryId(int $categoryId): Collection;

}
