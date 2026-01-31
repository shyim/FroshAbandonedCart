import './module/frosh-abandoned-carts';
import AbandonedCartStatisticsService from './service/abandoned-cart-statistics.service';
import FroshAbandonedCartAutomationApiService from './service/frosh-abandoned-cart-automation.api.service';

const { Application } = Shopware;

Application.addServiceProvider('froshAbandonedCartStatisticsService', (container) => {
    const initContainer = Application.getContainer('init');
    return new AbandonedCartStatisticsService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('froshAbandonedCartAutomationApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new FroshAbandonedCartAutomationApiService(initContainer.httpClient, container.loginService);
});
