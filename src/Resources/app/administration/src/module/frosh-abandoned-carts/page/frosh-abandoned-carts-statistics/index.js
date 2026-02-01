import template from './frosh-abandoned-carts-statistics.html.twig';
import './frosh-abandoned-carts-statistics.scss';

Shopware.Component.register('frosh-abandoned-carts-statistics', {
    template,

    inject: ['froshAbandonedCartStatisticsService'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            isLoading: true,
            totalCount: 0,
            totalValue: 0,
            todayCount: 0,
            todayValue: 0,
            historyDataCount: null,
            historyDataValue: null,
            topProducts: [],
            countDateRange: {
                label: '30Days',
                range: 30,
            },
            valueDateRange: {
                label: '30Days',
                range: 30,
            },
        };
    },

    computed: {
        rangesValueMap() {
            return [
                { label: '30Days', range: 30 },
                { label: '14Days', range: 14 },
                { label: '7Days', range: 7 },
            ];
        },

        availableRanges() {
            return this.rangesValueMap.map((range) => range.label);
        },

        chartOptionsCount() {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.getDateAgo(this.countDateRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 3,
                    labels: {
                        formatter: (value) => parseInt(value, 10),
                    },
                },
            };
        },

        chartOptionsValue() {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.getDateAgo(this.valueDateRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 5,
                    labels: {
                        formatter: (value) =>
                            Shopware.Utils.format.currency(
                                Number.parseFloat(value),
                                this.systemCurrencyISOCode,
                                2
                            ),
                    },
                },
            };
        },

        countSeries() {
            if (!this.historyDataCount) {
                return [];
            }

            const seriesData = this.historyDataCount.map((data) => ({
                x: this.parseDate(data.date),
                y: data.count,
            }));

            return [
                {
                    name: this.$tc(
                        'frosh-abandoned-carts.statistics.chartLabelCount'
                    ),
                    data: seriesData,
                },
            ];
        },

        valueSeries() {
            if (!this.historyDataValue) {
                return [];
            }

            const seriesData = this.historyDataValue.map((data) => ({
                x: this.parseDate(data.date),
                y: data.value,
            }));

            return [
                {
                    name: this.$tc(
                        'frosh-abandoned-carts.statistics.chartLabelValue'
                    ),
                    data: seriesData,
                },
            ];
        },

        systemCurrencyISOCode() {
            return Shopware.Context.app.systemCurrencyISOCode;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        today() {
            const today = Shopware.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);
            return today;
        },

        hasData() {
            return this.historyDataCount && this.historyDataCount.length > 0;
        },

        topProductsColumns() {
            return [
                {
                    property: 'label',
                    label: this.$tc(
                        'frosh-abandoned-carts.statistics.topProducts.columnProduct'
                    ),
                    primary: true,
                },
                {
                    property: 'count',
                    label: this.$tc(
                        'frosh-abandoned-carts.statistics.topProducts.columnCartCount'
                    ),
                    align: 'right',
                },
                {
                    property: 'totalQuantity',
                    label: this.$tc(
                        'frosh-abandoned-carts.statistics.topProducts.columnQuantity'
                    ),
                    align: 'right',
                },
                {
                    property: 'totalValue',
                    label: this.$tc(
                        'frosh-abandoned-carts.statistics.topProducts.columnValue'
                    ),
                    align: 'right',
                },
            ];
        },
    },

    created() {
        this.loadStatistics();
    },

    methods: {
        async loadStatistics() {
            this.isLoading = true;

            try {
                const maxRange = Math.max(
                    this.countDateRange.range,
                    this.valueDateRange.range
                );
                const since = this.formatDateToISO(
                    this.getDateAgo({ range: maxRange })
                );
                const timezone =
                    Shopware.Store.get('session').currentUser?.timeZone ??
                    'UTC';

                const data =
                    await this.froshAbandonedCartStatisticsService.getStatistics(
                        since,
                        timezone
                    );

                this.totalCount = data.totalCount;
                this.totalValue = data.totalValue;
                this.todayCount = data.todayCount;
                this.todayValue = data.todayValue;
                this.historyDataCount = data.dailyStats;
                this.historyDataValue = data.dailyStats;
                this.topProducts = data.topProducts || [];
            } catch (error) {
                console.error('Failed to load statistics:', error);
            } finally {
                this.isLoading = false;
            }
        },

        getDateAgo(range) {
            const date = Shopware.Utils.format.dateWithUserTimezone();
            date.setDate(date.getDate() - range.range);
            date.setHours(0, 0, 0, 0);
            return date;
        },

        formatDateToISO(date) {
            return Shopware.Utils.format.toISODate(date, false);
        },

        parseDate(date) {
            const parsedDate = new Date(
                date
                    .replace(/-/g, '/')
                    .replace('T', ' ')
                    .replace(/\..*|\+.*/, '')
            );
            return parsedDate.valueOf();
        },

        formatChartHeadlineDate(date) {
            const lastKnownLang =
                Shopware.Application.getContainer(
                    'factory'
                ).locale.getLastKnownLocale();
            return date.toLocaleDateString(lastKnownLang, {
                day: 'numeric',
                month: 'short',
            });
        },

        getCardSubtitle(range) {
            return `${this.formatChartHeadlineDate(this.getDateAgo(range))} - ${this.formatChartHeadlineDate(this.today)}`;
        },

        async onCountRangeUpdate(rangeLabel) {
            const range = this.rangesValueMap.find(
                (item) => item.label === rangeLabel
            );
            if (!range) {
                return;
            }

            this.countDateRange = range;
            await this.loadStatistics();
        },

        async onValueRangeUpdate(rangeLabel) {
            const range = this.rangesValueMap.find(
                (item) => item.label === rangeLabel
            );
            if (!range) {
                return;
            }

            this.valueDateRange = range;
            await this.loadStatistics();
        },

        formatPrice(price) {
            return this.currencyFilter(price, this.systemCurrencyISOCode, 2);
        },
    },
});
