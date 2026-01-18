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
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\DeliveryController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\CustomerAddressController;
use App\Http\Controllers\Api\Admin\TeamMemberController;
use App\Http\Controllers\Api\Admin\SliderController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Shared\ImageController;
use App\Http\Controllers\Api\Web\ProductController as WebProductController;
use App\Http\Controllers\Api\Web\CategoryController as WebCategoryController;
use App\Http\Controllers\Api\Web\AuthController;
use App\Http\Controllers\Api\Web\SocialMediaLinkController as WebSocialMediaLinkController;
use App\Http\Controllers\Api\Web\TeamMemberController as WebTeamMemberController;
use App\Http\Controllers\Api\Web\SliderController as WebSliderController;
use App\Http\Controllers\Api\Web\NotificationController as CustomerNotificationController;
use App\Http\Controllers\Api\Shared\ContactUsController;
use App\Http\Controllers\Api\UtilsController;
use App\Http\Controllers\Api\Mobile\auth\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\offers\OfferController as MobileOfferController;
use App\Http\Controllers\Api\Mobile\categories\CategoryController as MobileCategoryController;
use App\Http\Controllers\Api\Mobile\orders\OrderController as MobileOrderController;
use App\Http\Controllers\Api\Mobile\addresses\CustomerAddressController as MobileCustomerAddressController;
use App\Http\Controllers\Api\Mobile\locations\LocationController as MobileLocationController;
use App\Http\Controllers\Api\Mobile\payments\PaymentController as MobilePaymentController;
use App\Http\Controllers\Api\Mobile\profile\ProfileController as MobileProfileController;

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

      // Customer Addresses Management
      Route::controller(CustomerAddressController::class)->prefix('customer-addresses')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:customers,view');
         Route::post('/', 'store')->middleware('admin.permission:customers,add');
         Route::get('/customer/{customerId}', 'getByCustomer')->middleware('admin.permission:customers,view');
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
         Route::post('/{id}', 'update')->middleware('admin.permission:categories,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:categories,delete');
      });

            // Subcategories Management
      Route::controller(SubcategoryController::class)->prefix('subcategories')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:subcategories,view');
         Route::post('/', 'store')->middleware('admin.permission:subcategories,add');
         Route::get('/category/{categoryId}', 'getByCategory')->middleware('admin.permission:subcategories,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:subcategories,view');
         Route::post('/{id}', 'update')->middleware('admin.permission:subcategories,edit');
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
         Route::post('/{id}', 'update')->middleware('admin.permission:products,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:products,delete');
      });

            // Offers Management
      Route::controller(OfferController::class)->prefix('offers')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:offers,view');
         Route::post('/', 'store')->middleware('admin.permission:offers,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:offers,view');
         Route::post('/{id}', 'update')->middleware('admin.permission:offers,edit');
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
         Route::post('/{id}', 'update')->middleware('admin.permission:social_media_links,edit');
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

            // Team Members Management
      Route::controller(TeamMemberController::class)->prefix('team-members')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:team_members,view');
         Route::post('/', 'store')->middleware('admin.permission:team_members,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:team_members,view');
         Route::post('/{id}', 'update')->middleware('admin.permission:team_members,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:team_members,delete');
      });

            // Sliders Management
      Route::controller(SliderController::class)->prefix('sliders')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:sliders,view');
         Route::post('/', 'store')->middleware('admin.permission:sliders,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:sliders,view');
         Route::post('/{id}', 'update')->middleware('admin.permission:sliders,edit');
         Route::patch('/{id}/toggle-published', 'togglePublished')->middleware('admin.permission:sliders,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:sliders,delete');
      });

            // Orders Management
      Route::controller(OrderController::class)->prefix('orders')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:orders,view');
         Route::post('/', 'store')->middleware('admin.permission:orders,add');
         Route::get('/{id}', 'show')->middleware('admin.permission:orders,view');
         Route::put('/{id}', 'update')->middleware(['admin.permission:orders,edit', 'prevent.update.completed.order']);
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:orders,delete');
      });

      // Invoices Management
      Route::controller(InvoiceController::class)->prefix('invoices')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:invoices,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:invoices,view');
         Route::put('/{id}', 'update')->middleware('admin.permission:invoices,edit');
      });
      
            // Contact Us Management (Admin)
      Route::controller(ContactUsController::class)->prefix('contact-us')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:contact_us,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:contact_us,view');
         Route::patch('/{id}/mark-read', 'markAsRead')->middleware('admin.permission:contact_us,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:contact_us,delete');
      });

            // Settings Management
      Route::controller(SettingsController::class)->prefix('settings')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:settings,view');
         Route::put('/', 'update')->middleware('admin.permission:settings,edit');
      });

            // Activity Logs Management
      Route::controller(ActivityLogController::class)->prefix('activity-logs')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:activity_logs,view');
      });

            // Admin Notifications Management
      Route::controller(AdminNotificationController::class)->prefix('notifications')->group(function () {
         Route::get('/', 'index')->middleware('admin.permission:notifications,view');
         Route::get('/unread', 'unread')->middleware('admin.permission:notifications,view');
         Route::get('/unread-count', 'unreadCount')->middleware('admin.permission:notifications,view');
         Route::get('/{id}', 'show')->middleware('admin.permission:notifications,view');
         Route::patch('/{id}/mark-read', 'markAsRead')->middleware('admin.permission:notifications,edit');
         Route::patch('/mark-all-read', 'markAllAsRead')->middleware('admin.permission:notifications,edit');
         Route::delete('/{id}', 'destroy')->middleware('admin.permission:notifications,delete');
      });


});

// Payment Callback Routes (Public - called by Upayments)
Route::controller(PaymentController::class)->prefix('payments/callback')->group(function () {
   Route::get('/success', 'success')->name('payments.success');
   Route::get('/cancel', 'cancel')->name('payments.cancel');
   Route::post('/notification', 'notification')->name('payments.notification');
});

// Contact Us Routes (Public)
Route::controller(ContactUsController::class)->prefix('contact-us')->group(function () {
   Route::post('/', 'store');
});

// Utils Routes (Public)
Route::controller(UtilsController::class)->prefix('utils')->group(function () {
   Route::get('/categories', 'getCategories');
   Route::get('/categories/{categoryId}/subcategories', 'getSubcategories');
});

// Location Routes (Public)
Route::controller(GovernorateController::class)->prefix('governorates')->group(function () {
   Route::get('/country/{countryId}', 'getByCountry');
});

Route::controller(AreaController::class)->prefix('admin/areas')->group(function () {
   Route::get('/governorate/{governorateId}', 'getByGovernorate');
});

// Career Application Routes (Public)
Route::controller(CareerController::class)->prefix('careers')->group(function () {
   Route::post('/', 'store');
});

// Web Product Routes (Public)
Route::controller(WebProductController::class)->prefix('products')->group(function () {
   Route::get('/', 'getAllProductsWithVariants');
   Route::get('/variants-as-products', 'getAllVariantsAsProducts');
   Route::get('/most-selling', 'getMostSellingProducts');
   Route::get('/{id}', 'getProductWithVariants');
   Route::get('/category/{categoryId}', 'getProductsByCategoryWithVariants');
   Route::get('/subcategory/{subcategoryId}', 'getProductsBySubcategoryWithVariants');
});

// Web Category Routes (Public)
Route::controller(WebCategoryController::class)->group(function () {
   Route::get('/categories', 'getAllCategories');
   Route::get('/subcategories', 'getAllSubcategories');
   Route::get('categories/{categoryId}/subcategories', 'getSubcategoriesByCategory');
});

// Web Social Media Links Routes (Public)
Route::controller(WebSocialMediaLinkController::class)->prefix('social-media-links')->group(function () {
   Route::get('/', 'getAllActiveLinks');
});

// Web Team Members Routes (Public)
Route::controller(WebTeamMemberController::class)->prefix('team-members')->group(function () {
   Route::get('/', 'getAll');
});

// Web Sliders Routes (Public)
Route::controller(WebSliderController::class)->prefix('sliders')->group(function () {
   Route::get('/', 'getAllPublished');
});

// Customer Authentication Routes (Public)
Route::controller(AuthController::class)->prefix('auth')->group(function () {
   Route::post('/register', 'register');
   Route::post('/login', 'login');
   Route::post('/check-customer', 'checkCustomer');
});

// Customer Authentication Routes (Protected)
Route::middleware('api.auth')->controller(AuthController::class)->prefix('auth')->group(function () {
   Route::post('/logout', 'logout');
   Route::get('/profile', 'profile');
});

// Customer Notifications Routes (Protected)
Route::middleware('api.auth')->controller(CustomerNotificationController::class)->prefix('notifications')->group(function () {
   Route::get('/', 'index');
   Route::get('/unread', 'unread');
   Route::get('/unread-count', 'unreadCount');
   Route::get('/{id}', 'show');
   Route::patch('/{id}/mark-read', 'markAsRead');
   Route::patch('/mark-all-read', 'markAllAsRead');
   Route::delete('/{id}', 'destroy');
});


// =====================================================================================================
// ================== Mobile Authentication Routes ======================================================
// =====================================================================================================
Route::prefix('mobile/auth')->group(function () {
   Route::post('/send-otp', [MobileAuthController::class, 'sendOtp']);
   Route::post('/verify-otp', [MobileAuthController::class, 'verifyOtp']);
   Route::post('/resend-otp', [MobileAuthController::class, 'resendOtp']);
   Route::post('/complete-registration', [MobileAuthController::class, 'completeRegistration'])->middleware('api.auth');
   Route::post('/logout', [MobileAuthController::class, 'logout'])->middleware('api.auth');
   Route::delete('/account', [MobileAuthController::class, 'deleteAccount'])->middleware('api.auth');
});
// =====================================================================================================
// ================== Mobile  Routes (Public) =====================================================
// =====================================================================================================
Route::prefix('mobile/offers')->group(function () {
      Route::get('/', [MobileOfferController::class, 'index']);
      Route::get('/{id}', [MobileOfferController::class, 'show']);
   });

Route::prefix('mobile/categories')->group(function () {
      Route::get('/', [MobileCategoryController::class, 'getAllCategories']);
      Route::get('/subcategories', [MobileCategoryController::class, 'getAllSubcategories']);
      Route::get('/{categoryId}/subcategories', [MobileCategoryController::class, 'getSubcategoriesByCategory']);
   });

// =====================================================================================================
// ================== Mobile Order Routes (Protected) ======================================================
// =====================================================================================================
Route::middleware('api.auth')->prefix('mobile/orders')->group(function () {
   Route::post('/', [MobileOrderController::class, 'store']);
   Route::get('/', [MobileOrderController::class, 'index']);
   Route::get('/{id}', [MobileOrderController::class, 'show']);
});


Route::middleware('api.auth')->prefix('mobile/customer-addresses')->group(function () {
   Route::get('/', [MobileCustomerAddressController::class, 'index']);
   Route::post('/', [MobileCustomerAddressController::class, 'store']);
   Route::get('/{id}', [MobileCustomerAddressController::class, 'show']);
   Route::put('/{id}', [MobileCustomerAddressController::class, 'update']);
   Route::delete('/{id}', [MobileCustomerAddressController::class, 'destroy']);
});

Route::prefix('mobile/locations')->group(function () {
   Route::get('/countries', [MobileLocationController::class, 'getCountries']);
   Route::get('/countries/{countryId}/governorates', [MobileLocationController::class, 'getGovernoratesByCountry']);
   Route::get('/governorates/{governorateId}/areas', [MobileLocationController::class, 'getAreasByGovernorate']);
});

Route::middleware('api.auth')->prefix('mobile/payments')->group(function () {
   Route::get('/', [MobilePaymentController::class, 'index']);
});

// =====================================================================================================
// ================== Mobile Profile Routes (Protected) ======================================================
// =====================================================================================================
Route::middleware('api.auth')->prefix('mobile/profile')->group(function () {
   Route::get('/', [MobileProfileController::class, 'show']);
   Route::put('/', [MobileProfileController::class, 'update']);
});
   
Route::middleware('api.auth')->group(function () {
});
// =====================================================================================================
// =====================================================================================================