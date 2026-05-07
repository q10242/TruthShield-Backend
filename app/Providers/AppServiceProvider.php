<?php

namespace App\Providers;

use App\Support\AdminChineseLabels;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('zh_TW');

        TextInput::configureUsing(fn (TextInput $component) => $component->label(AdminChineseLabels::field($component->getName())));
        Textarea::configureUsing(fn (Textarea $component) => $component->label(AdminChineseLabels::field($component->getName())));
        Select::configureUsing(fn (Select $component) => $component->label(AdminChineseLabels::field($component->getName())));
        Toggle::configureUsing(fn (Toggle $component) => $component->label(AdminChineseLabels::field($component->getName())));
        DateTimePicker::configureUsing(fn (DateTimePicker $component) => $component->label(AdminChineseLabels::field($component->getName())));
        TextColumn::configureUsing(fn (TextColumn $column) => $column->label(AdminChineseLabels::field($column->getName())));
        IconColumn::configureUsing(fn (IconColumn $column) => $column->label(AdminChineseLabels::field($column->getName())));
        TernaryFilter::configureUsing(fn (TernaryFilter $filter) => $filter->label(AdminChineseLabels::field($filter->getName())));
        Table::configureUsing(fn (Table $table) => $table->actionsColumnLabel('操作'));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('hover', fn (Request $request) => Limit::perMinute(180)->by($request->ip()));
        RateLimiter::for('vote', function (Request $request) {
            $max = $request->user()?->risk_status === 'normal' ? 20 : 6;
            return Limit::perMinute($max)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('reaction', function (Request $request) {
            $max = $request->user()?->risk_status === 'normal' ? 40 : 10;
            return Limit::perMinute($max)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('email-intake', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);
    }
}
