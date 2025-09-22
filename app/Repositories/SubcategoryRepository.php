<?php

namespace App\Repositories;

use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SubcategoryRepository implements SubcategoryRepositoryInterface
{
   protected $model;

   public function __construct(Subcategory $subcategory)
   {
      $this->model = $subcategory;
   }

   /**
    * Get all subcategories with pagination, search and filters
    */
   public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
   {
      $query = $this->model->with('category');

      // Search functionality
      if (isset($filters['search']) && !empty($filters['search'])) {
         $search = $filters['search'];
         $query->where(function ($q) use ($search) {
               $q->where('name_en', 'LIKE', "%{$search}%")
               ->orWhere('name_ar', 'LIKE', "%{$search}%");
         });
      }

      // Filter by status
      if (isset($filters['status']) && $filters['status'] !== '') {
         if ($filters['status'] === 'active') {
               $query->where('is_active', true);
         } elseif ($filters['status'] === 'inactive') {
               $query->where('is_active', false);
         }
      }

      // Filter by category
      if (isset($filters['category_id']) && $filters['category_id'] !== '') {
         $query->where('category_id', $filters['category_id']);
      }

      // Default sorting by created_at desc
      $query->orderBy('created_at', 'desc');

      return $query->paginate($perPage);
   }

   /**
    * Get all subcategories
    */
   public function getAll(): Collection
   {
      return $this->model->with('category')->get();
   }

   /**
    * Get subcategory by ID
    */
   public function findById(int $id): ?Subcategory
   {
      return $this->model->with('category')->find($id);
   }

   /**
    * Create a new subcategory
    */
   public function create(array $data): Subcategory
   {
      return $this->model->create($data);
   }

   /**
    * Update subcategory
    */
   public function update(int $id, array $data): ?Subcategory
   {
      $subcategory = $this->model->find($id);
      
      if (!$subcategory) {
         return null;
      }

      $subcategory->update($data);
      return $subcategory->load('category');
   }

   /**
    * Delete subcategory
    */
   public function delete(int $id): bool
   {
      $subcategory = $this->model->find($id);
      
      if (!$subcategory) {
         return false;
      }

      return $subcategory->delete();
   }

   /**
    * Get subcategories by category ID
    */
   public function getByCategoryId(int $categoryId): Collection
   {
      return $this->model->with('category')->where('category_id', $categoryId)->get();
   }

   /**
    * Get subcategories by category ID (alias for getByCategoryId)
    */
   public function getByCategory(int $categoryId): Collection
   {
      return $this->getByCategoryId($categoryId);
   }

   /**
    * Get active subcategories only
    */
   public function getActive(): Collection
   {
      return $this->model->with('category')->where('is_active', true)->get();
   }

   /**
    * Get inactive subcategories only
    */
   public function getInactive(): Collection
   {
      return $this->model->with('category')->where('is_active', false)->get();
   }
}
