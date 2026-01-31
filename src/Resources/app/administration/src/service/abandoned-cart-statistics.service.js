const ApiService = Shopware.Classes.ApiService;

class AbandonedCartStatisticsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'frosh-abandoned-cart') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'froshAbandonedCartStatisticsService';
    }

    getStatistics(since, timezone) {
        return this.httpClient
            .get(`/_action/${this.apiEndpoint}/statistics`, {
                params: { since, timezone },
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

export default AbandonedCartStatisticsService;
