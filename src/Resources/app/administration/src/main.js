import './module/frosh-abandoned-carts';
import './extension/sw-customer-detail';
import './extension/sw-customer-detail-carts';
import AbandonedCartStatisticsService from './service/abandoned-cart-statistics.service';
import FroshAbandonedCartAutomationApiService from './service/frosh-abandoned-cart-automation.api.service';

const { Module } = Shopware;

Module.register('frosh-abandoned-carts-customer-extension', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.customer.detail') {
            currentRoute.children.push({
                name: 'sw.customer.detail.abandoned_carts',
                path: '/sw/customer/detail/:id/abandoned-carts',
                component: 'sw-customer-detail-carts',
                meta: {
                    parentPath: 'sw.customer.index',
                    privilege: 'customer.viewer',
                },
            });
        }

        next(currentRoute);
    },
});

const { Application } = Shopware;

Application.addServiceProvider(
    'froshAbandonedCartStatisticsService',
    (container) => {
        const initContainer = Application.getContainer('init');
        return new AbandonedCartStatisticsService(
            initContainer.httpClient,
            container.loginService
        );
    }
);

Application.addServiceProvider(
    'froshAbandonedCartAutomationApiService',
    (container) => {
        const initContainer = Application.getContainer('init');
        return new FroshAbandonedCartAutomationApiService(
            initContainer.httpClient,
            container.loginService
        );
    }
);
