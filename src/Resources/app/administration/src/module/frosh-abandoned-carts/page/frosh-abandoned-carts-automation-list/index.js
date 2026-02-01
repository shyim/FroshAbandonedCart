import template from './frosh-abandoned-carts-automation-list.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('frosh-abandoned-carts-automation-list', {
    template,

    inject: ['repositoryFactory'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            automations: null,
            isLoading: true,
            sortBy: 'priority',
            sortDirection: 'DESC',
            searchTerm: '',
        };
    },

    computed: {
        automationRepository() {
            return this.repositoryFactory.create(
                'frosh_abandoned_cart_automation'
            );
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$tc(
                        'frosh-abandoned-carts.automations.columns.name'
                    ),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$tc(
                        'frosh-abandoned-carts.automations.columns.active'
                    ),
                    allowResize: true,
                    align: 'center',
                    width: '100px',
                },
                {
                    property: 'salesChannel.name',
                    label: this.$tc(
                        'frosh-abandoned-carts.automations.columns.salesChannel'
                    ),
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: this.$tc(
                        'frosh-abandoned-carts.automations.columns.createdAt'
                    ),
                    allowResize: true,
                },
            ];
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('salesChannel');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },
    },

    created() {
        this.loadAutomations();
    },

    methods: {
        async loadAutomations() {
            this.isLoading = true;

            try {
                this.automations = await this.automationRepository.search(
                    this.defaultCriteria,
                    Shopware.Context.api
                );
            } catch (error) {
                console.error('Failed to load automations:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async onDelete(item) {
            try {
                await this.automationRepository.delete(
                    item.id,
                    Shopware.Context.api
                );
                await this.loadAutomations();
            } catch (error) {
                console.error('Failed to delete automation:', error);
            }
        },

        onSearch(searchTerm) {
            this.searchTerm = searchTerm;
            this.loadAutomations();
        },
    },
});
