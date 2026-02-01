import template from './sw-customer-detail-carts.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('sw-customer-detail-carts', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            carts: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        };
    },

    computed: {
        cartRepository() {
            return this.repositoryFactory.create('frosh_abandoned_cart');
        },

        columns() {
            return [
                {
                    property: 'totalPrice',
                    label: this.$tc(
                        'frosh-abandoned-carts.customer.columnTotalPrice'
                    ),
                    allowResize: true,
                    primary: true,
                    align: 'right',
                },
                {
                    property: 'lineItemCount',
                    label: this.$tc(
                        'frosh-abandoned-carts.customer.columnLineItemCount'
                    ),
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'salesChannel.name',
                    label: this.$tc(
                        'frosh-abandoned-carts.customer.columnSalesChannel'
                    ),
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: this.$tc(
                        'frosh-abandoned-carts.customer.columnCreatedAt'
                    ),
                    allowResize: true,
                },
            ];
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('customerId', this.customer.id));
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('lineItems');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    watch: {
        customer() {
            this.loadCarts();
        },
    },

    created() {
        this.loadCarts();
    },

    methods: {
        async loadCarts() {
            this.isLoading = true;

            try {
                this.carts = await this.cartRepository.search(
                    this.defaultCriteria,
                    Shopware.Context.api
                );
            } catch (error) {
                console.error('Failed to load carts:', error);
            } finally {
                this.isLoading = false;
            }
        },
    },
});
