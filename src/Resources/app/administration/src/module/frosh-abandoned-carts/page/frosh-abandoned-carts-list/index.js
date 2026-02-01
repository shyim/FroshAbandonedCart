import template from './frosh-abandoned-carts-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Shopware.Component.register('frosh-abandoned-carts-list', {
    template,

    inject: ['repositoryFactory', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            abandonedCarts: null,
            isLoading: true,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            filterCriteria: [],
            defaultFilters: ['sales-channel-filter', 'customer-filter'],
            storeKey: 'grid.filter.frosh_abandoned_cart',
            activeFilterNumber: 0,
        };
    },

    computed: {
        abandonedCartRepository() {
            return this.repositoryFactory.create('frosh_abandoned_cart');
        },

        columns() {
            return [
                {
                    property: 'customer.email',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.columnCustomer'
                    ),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'totalPrice',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.columnTotalPrice'
                    ),
                    allowResize: true,
                },
                {
                    property: 'salesChannel.name',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.columnSalesChannel'
                    ),
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.columnCreatedAt'
                    ),
                    allowResize: true,
                },
            ];
        },

        listFilterOptions() {
            return {
                'sales-channel-filter': {
                    property: 'salesChannel',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.filterSalesChannel'
                    ),
                    placeholder: this.$tc(
                        'frosh-abandoned-carts.list.filterSalesChannelPlaceholder'
                    ),
                },
                'customer-filter': {
                    property: 'customer',
                    label: this.$tc(
                        'frosh-abandoned-carts.list.filterCustomer'
                    ),
                    placeholder: this.$tc(
                        'frosh-abandoned-carts.list.filterCustomerPlaceholder'
                    ),
                    labelProperty: 'email',
                },
            };
        },

        listFilters() {
            return this.filterFactory.create(
                'frosh_abandoned_cart',
                this.listFilterOptions
            );
        },

        defaultCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('customer');
            criteria.addAssociation('salesChannel');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            this.filterCriteria.forEach((filter) => {
                criteria.addFilter(filter);
            });

            return criteria;
        },
    },

    watch: {
        defaultCriteria: {
            handler() {
                this.getList();
            },
            deep: true,
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                const criteria = await Shopware.Service(
                    'filterService'
                ).mergeWithStoredFilters(this.storeKey, this.defaultCriteria);

                const result = await this.abandonedCartRepository.search(
                    criteria,
                    Shopware.Context.api
                );
                this.abandonedCarts = result;
                this.total = result.total;
            } catch (error) {
                console.error('Failed to load abandoned carts:', error);
            } finally {
                this.isLoading = false;
            }
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
            this.activeFilterNumber = criteria.length;
        },

        onRefresh() {
            this.getList();
        },

        getCustomerName(item) {
            if (!item.customer) {
                return '-';
            }
            return `${item.customer.firstName} ${item.customer.lastName} (${item.customer.email})`;
        },

        formatPrice(price, currencyIsoCode) {
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: currencyIsoCode || 'EUR',
            }).format(price);
        },
    },
});
