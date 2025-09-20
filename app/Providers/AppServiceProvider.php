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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
