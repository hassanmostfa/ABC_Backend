<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\CustomerRepositoryInterface;
use App\Repositories\CustomerRepository;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepositoryInterface;
use App\Repositories\SubcategoryRepository;
use App\Repositories\ProductRepositoryInterface;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepositoryInterface;
use App\Repositories\ProductVariantRepository;
use App\Repositories\OfferRepositoryInterface;
use App\Repositories\OfferRepository;
use App\Repositories\CharityRepositoryInterface;
use App\Repositories\CharityRepository;
use App\Repositories\CountryRepositoryInterface;
use App\Repositories\CountryRepository;
use App\Repositories\GovernorateRepositoryInterface;
use App\Repositories\GovernorateRepository;
use App\Repositories\AreaRepositoryInterface;
use App\Repositories\AreaRepository;
use App\Repositories\ContactUsRepositoryInterface;
use App\Repositories\ContactUsRepository;
use App\Repositories\SocialMediaLinkRepositoryInterface;
use App\Repositories\SocialMediaLinkRepository;
use App\Repositories\CareerRepositoryInterface;
use App\Repositories\CareerRepository;
use App\Repositories\OrderRepositoryInterface;
use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepositoryInterface;
use App\Repositories\OrderItemRepository;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepositoryInterface;
use App\Repositories\PaymentRepository;
use App\Repositories\DeliveryRepositoryInterface;
use App\Repositories\DeliveryRepository;
use App\Repositories\CustomerAddressRepositoryInterface;
use App\Repositories\CustomerAddressRepository;
use App\Repositories\TeamMemberRepositoryInterface;
use App\Repositories\TeamMemberRepository;
use App\Repositories\SliderRepositoryInterface;
use App\Repositories\SliderRepository;
use App\Repositories\NotificationRepositoryInterface;
use App\Repositories\NotificationRepository;
use App\Repositories\FaqRepositoryInterface;
use App\Repositories\FaqRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Helpers/DateTimeHelper.php');

        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(SubcategoryRepositoryInterface::class, SubcategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ProductVariantRepositoryInterface::class, ProductVariantRepository::class);
        $this->app->bind(OfferRepositoryInterface::class, OfferRepository::class);
        $this->app->bind(CharityRepositoryInterface::class, CharityRepository::class);
        $this->app->bind(CountryRepositoryInterface::class, CountryRepository::class);
        $this->app->bind(GovernorateRepositoryInterface::class, GovernorateRepository::class);
        $this->app->bind(AreaRepositoryInterface::class, AreaRepository::class);
        $this->app->bind(ContactUsRepositoryInterface::class, ContactUsRepository::class);
        $this->app->bind(SocialMediaLinkRepositoryInterface::class, SocialMediaLinkRepository::class);
        $this->app->bind(CareerRepositoryInterface::class, CareerRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OrderItemRepositoryInterface::class, OrderItemRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(DeliveryRepositoryInterface::class, DeliveryRepository::class);
        $this->app->bind(CustomerAddressRepositoryInterface::class, CustomerAddressRepository::class);
        $this->app->bind(TeamMemberRepositoryInterface::class, TeamMemberRepository::class);
        $this->app->bind(SliderRepositoryInterface::class, SliderRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(FaqRepositoryInterface::class, FaqRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
