<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\SubcategoryController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductVariantController;
use App\Http\Controllers\Api\Admin\OfferController;
use App\Http\Controllers\Api\Admin\CharityController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\GovernorateController;
use App\Http\Controllers\Api\Admin\AreaController;
use App\Http\Controllers\Api\Admin\SocialMediaLinkController;
use App\Http\Controllers\Api\Admin\CareerController;
use App\Http\Controllers\Api\Shared\ImageController;
use App\Http\Controllers\ContactUsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


   // Admin Authentication Routes (Public)
Route::prefix('admin')->group(function () {
   Route::post('/login', [AdminController::class, 'login']);
});

// Admin Management Routes (Protected)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
   // Admin Authentication (Protected)
   Route::controller(AdminController::class)->group(function () {
      Route::post('/logout', 'logout');
      Route::get('/profile', 'profile');
   });

   // Admin Users Management
   Route::controller(AdminController::class)->prefix('admins')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:admins,view');
         Route::post('/', 'store')->middleware('admin.permission:admins,add');
         Route::get('/roles', 'getRoles')->middleware('admin.permission:roles,view'); // This must come before {id} routes
         Route::get('/{id}', 'show')->middleware('admin.permission:admins,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:admins,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:admins,delete');
      });

         // Roles Management
   Route::controller(RoleController::class)->prefix('roles')->group(function () {
      Route::get('/', 'index')->middleware('admin.permission:roles,view');
      Route::post('/', 'store')->middleware('admin.permission:roles,add');
      Route::get('/permissions-structure', 'getPermissionsStructure')->middleware('admin.permission:roles,view');
      Route::get('/{id}', 'show')->middleware('admin.permission:roles,view');
      Route::put('/{id}', 'update')->middleware('admin.permission:roles,edit');
      Route::delete('/{id}', 'destroy')->middleware('admin.permission:roles,delete');
   });

// Permissions Management
   Route::controller(PermissionController::class)->prefix('permissions')->group(function () {
      Route::get('/', 'index')->middleware('admin.permission:permissions,view');
      Route::get('/categories', 'getCategories')->middleware('admin.permission:permissions,view');
      Route::get('/items', 'getAllItems')->middleware('admin.permission:permissions,view');
      Route::get('/categories/{category}/items', 'getItemsByCategory')->middleware('admin.permission:permissions,view');
      
      // Permission Categories
      Route::post('/categories', 'storeCategory')->middleware('admin.permission:permissions,add');
      Route::put('/categories/{category}', 'updateCategory')->middleware('admin.permission:permissions,edit');
      Route::delete('/categories/{category}', 'destroyCategory')->middleware('admin.permission:permissions,delete');
      
      // Permission Items
      Route::post('/items', 'storeItem')->middleware('admin.permission:permissions,add');
      Route::put('/items/{item}', 'updateItem')->middleware('admin.permission:permissions,edit');
      Route::delete('/items/{item}', 'destroyItem')->middleware('admin.permission:permissions,delete');
   });

            // Customers Management
      Route::controller(CustomerController::class)->prefix('customers')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:customers,view');
         Route::post('/', 'store')->middleware('admin.permission:customers,add');
         Route::get('/active', 'active')->middleware('admin.permission:customers,view');
         Route::get('/inactive', 'inactive')->middleware('admin.permission:customers,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:customers,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:customers,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:customers,delete');
      });

            // Categories Management
      Route::controller(CategoryController::class)->prefix('categories')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:categories,view');
         Route::post('/', 'store')->middleware('admin.permission:categories,add');
         Route::get('/active', 'active')->middleware('admin.permission:categories,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:categories,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:categories,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:categories,delete');
      });

            // Subcategories Management
      Route::controller(SubcategoryController::class)->prefix('subcategories')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:subcategories,view');
         Route::post('/', 'store')->middleware('admin.permission:subcategories,add');
         Route::get('/category/{categoryId}', 'getByCategory')->middleware('admin.permission:subcategories,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:subcategories,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:subcategories,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:subcategories,delete');
      });

            // Products Management
      Route::controller(ProductController::class)->prefix('products')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:products,view');
         Route::post('/', 'store')->middleware('admin.permission:products,add');
         Route::get('/category/{categoryId}', 'getByCategory')->middleware('admin.permission:products,view');
         Route::get('/subcategory/{subcategoryId}', 'getBySubcategory')->middleware('admin.permission:products,view');
         Route::get('/with-variants', 'getWithVariants')->middleware('admin.permission:products,view');
         Route::get('/without-variants', 'getWithoutVariants')->middleware('admin.permission:products,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:products,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:products,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:products,delete');
      });

            // Offers Management
      Route::controller(OfferController::class)->prefix('offers')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:offers,view');
         Route::post('/', 'store')->middleware('admin.permission:offers,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:offers,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:offers,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:offers,delete');
      });

            // Charities Management
      Route::controller(CharityController::class)->prefix('charities')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:charities,view');
         Route::post('/', 'store')->middleware('admin.permission:charities,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:charities,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:charities,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:charities,delete');
      });

            // Countries Management
      Route::controller(CountryController::class)->prefix('countries')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:countries,view');
         Route::post('/', 'store')->middleware('admin.permission:countries,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:countries,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:countries,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:countries,delete');
      });

            // Governorates Management
      Route::controller(GovernorateController::class)->prefix('governorates')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:governorates,view');
         Route::post('/', 'store')->middleware('admin.permission:governorates,add');
         Route::get('/country/{countryId}', 'getByCountry')->middleware('admin.permission:governorates,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:governorates,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:governorates,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:governorates,delete');
      });

            // Areas Management
      Route::controller(AreaController::class)->prefix('areas')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:areas,view');
         Route::post('/', 'store')->middleware('admin.permission:areas,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:areas,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:areas,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:areas,delete');
      });

            // Social Media Links Management
      Route::controller(SocialMediaLinkController::class)->prefix('social-media-links')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:social_media_links,view');
         Route::post('/', 'store')->middleware('admin.permission:social_media_links,add');
         Route::get('/active', 'active')->middleware('admin.permission:social_media_links,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:social_media_links,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:social_media_links,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:social_media_links,delete');
      });

            // Careers Management
      Route::controller(CareerController::class)->prefix('careers')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:careers,view');
         Route::post('/', 'store')->middleware('admin.permission:careers,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:careers,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:careers,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:careers,delete');
      });

            // Contact Us Management (Admin)
      Route::controller(ContactUsController::class)->prefix('contact-us')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:contact_us,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:contact_us,view');
         Route::patch('/{id}/mark-read', 'markAsRead')->middleware('admin.permission:contact_us,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:contact_us,delete');
      });
});


// Shared Routes (No Authentication Required)
Route::controller(ImageController::class)->prefix('file')->group(function () {
   Route::post('/upload', 'upload');
});

// Contact Us Routes (Public)
Route::controller(ContactUsController::class)->prefix('contact-us')->group(function () {
   Route::post('/', 'store');
});

// Career Application Routes (Public)
Route::controller(CareerController::class)->prefix('careers')->group(function () {
   Route::post('/', 'store');
});
