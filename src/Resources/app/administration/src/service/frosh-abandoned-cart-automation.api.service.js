const ApiService = Shopware.Classes.ApiService;

export default class FroshAbandonedCartAutomationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'frosh-abandoned-cart') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'froshAbandonedCartAutomationApiService';
    }

    testAutomation(conditions, salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/frosh-abandoned-cart/automation/test',
                {
                    conditions,
                    salesChannelId,
                },
                {
                    headers,
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
