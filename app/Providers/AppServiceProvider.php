<?php

namespace App\Providers;

use App\Support\OctopusApiToken;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Customers\CustomerRepositoryInterface;
use App\Repositories\Customers\CustomerRepository;
use App\Repositories\Categories\CategoryRepositoryInterface;
use App\Repositories\Categories\CategoryRepository;
use App\Repositories\Subcategories\SubcategoryRepositoryInterface;
use App\Repositories\Subcategories\SubcategoryRepository;
use App\Repositories\Products\ProductRepositoryInterface;
use App\Repositories\Products\ProductRepository;
use App\Repositories\Products\ProductVariantRepositoryInterface;
use App\Repositories\Products\ProductVariantRepository;
use App\Repositories\Offers\OfferRepositoryInterface;
use App\Repositories\Offers\OfferRepository;
use App\Repositories\Charities\CharityRepositoryInterface;
use App\Repositories\Charities\CharityRepository;
use App\Repositories\Countries\CountryRepositoryInterface;
use App\Repositories\Countries\CountryRepository;
use App\Repositories\Governorates\GovernorateRepositoryInterface;
use App\Repositories\Governorates\GovernorateRepository;
use App\Repositories\Areas\AreaRepositoryInterface;
use App\Repositories\Areas\AreaRepository;
use App\Repositories\ContactUs\ContactUsRepositoryInterface;
use App\Repositories\ContactUs\ContactUsRepository;
use App\Repositories\SocialMediaLinks\SocialMediaLinkRepositoryInterface;
use App\Repositories\SocialMediaLinks\SocialMediaLinkRepository;
use App\Repositories\Careers\CareerRepositoryInterface;
use App\Repositories\Careers\CareerRepository;
use App\Repositories\Orders\OrderRepositoryInterface;
use App\Repositories\Orders\OrderRepository;
use App\Repositories\Orders\OrderItemRepositoryInterface;
use App\Repositories\Orders\OrderItemRepository;
use App\Repositories\Invoices\InvoiceRepositoryInterface;
use App\Repositories\Invoices\InvoiceRepository;
use App\Repositories\Payments\PaymentRepositoryInterface;
use App\Repositories\Payments\PaymentRepository;
use App\Repositories\Deliveries\DeliveryRepositoryInterface;
use App\Repositories\Deliveries\DeliveryRepository;
use App\Repositories\Customers\CustomerAddressRepositoryInterface;
use App\Repositories\Customers\CustomerAddressRepository;
use App\Repositories\TeamMembers\TeamMemberRepositoryInterface;
use App\Repositories\TeamMembers\TeamMemberRepository;
use App\Repositories\Sliders\SliderRepositoryInterface;
use App\Repositories\Sliders\SliderRepository;
use App\Repositories\Notifications\NotificationRepositoryInterface;
use App\Repositories\Notifications\NotificationRepository;
use App\Repositories\Faqs\FaqRepositoryInterface;
use App\Repositories\Faqs\FaqRepository;
use App\Repositories\Coupons\CouponRepositoryInterface;
use App\Repositories\Coupons\CouponRepository;
use App\Repositories\Feedbacks\FeedbackRepositoryInterface;
use App\Repositories\Feedbacks\FeedbackRepository;
use App\Repositories\Complaints\ComplaintRepositoryInterface;
use App\Repositories\Complaints\ComplaintRepository;

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
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(FeedbackRepositoryInterface::class, FeedbackRepository::class);
        $this->app->bind(ComplaintRepositoryInterface::class, ComplaintRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OctopusApiToken::assertSafeForBoot(config('services.octopus.access_token'));
    }
}
