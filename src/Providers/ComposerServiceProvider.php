<?php

namespace OpenDominion\Providers;

use Cache;
use Illuminate\Contracts\View\View;
use OpenDominion\Calculators\Dominion\Actions\TechCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Helpers\NotificationHelper;
use OpenDominion\Models\Council;
use OpenDominion\Models\Forum;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\SelectorService;

class ComposerServiceProvider extends AbstractServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer('layouts.topnav', function (View $view) {
            $view->with('selectorService', app(SelectorService::class));
        });

        view()->composer('partials.main-sidebar', function (View $view) {
            $selectorService = app(SelectorService::class);

            if (!$selectorService->hasUserSelectedDominion()) {
                return;
            }

            /** @var Dominion $dominion */
            $dominion = $selectorService->getUserSelectedDominion();

            $councilLastRead = $dominion->council_last_read;
            $councilUnreadCount = $dominion->realm
                ->councilThreads()
                ->with(['posts' => function($query) use ($councilLastRead) {
                    if ($councilLastRead !== null) {
                        $query->where('created_at', '>', $councilLastRead);
                    }
                }])
                ->get()
                ->map(static function (Council\Thread $thread) use ($councilLastRead) {
                    $unreadCount = $thread->posts->count();

                    if ($thread->created_at > $councilLastRead) {
                        $unreadCount++;
                    }

                    return $unreadCount;
                })
                ->sum();
            $view->with('councilUnreadCount', $councilUnreadCount);

            $forumLastRead = $dominion->forum_last_read;
            $forumUnreadCount = $dominion->round
                ->forumThreads()
                ->with(['posts' => function($query) use ($forumLastRead) {
                    if ($forumLastRead !== null) {
                        $query->where('created_at', '>', $forumLastRead);
                    }
                }])
                ->get()
                ->map(static function (Forum\Thread $thread) use ($forumLastRead) {
                    $unreadCount = $thread->posts->count();

                    if ($thread->created_at > $forumLastRead) {
                        $unreadCount++;
                    }

                    return $unreadCount;
                })
                ->sum();
            $view->with('forumUnreadCount', $forumUnreadCount);

            // Show icon for techs
            $techCalculator = app(TechCalculator::class);
            $techCost = $techCalculator->getTechCost($dominion);
            $unlockableTechCount = floor($dominion->resource_tech / $techCost);
            $view->with('unlockableTechCount', $unlockableTechCount);
        });

        view()->composer('partials.main-footer', function (View $view) {
            $version = (Cache::has('version-html') ? Cache::get('version-html') : 'unknown');
            $view->with('version', $version);
        });

        view()->composer('partials.notification-nav', function (View $view) {
            $view->with('notificationHelper', app(NotificationHelper::class));
        });

        // todo: do we need this here in this class?
        view()->composer('partials.resources-overview', function (View $view) {
            $view->with('networthCalculator', app(NetworthCalculator::class));
        });
    }
}
