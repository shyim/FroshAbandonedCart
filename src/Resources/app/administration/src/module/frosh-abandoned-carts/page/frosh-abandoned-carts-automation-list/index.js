import template from './frosh-abandoned-carts-automation-list.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('frosh-abandoned-carts-automation-list', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            automations: null,
            isLoading: true,
            sortBy: 'priority',
            sortDirection: 'DESC',
        };
    },

    computed: {
        automationRepository() {
            return this.repositoryFactory.create('frosh_abandoned_cart_automation');
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.name'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.active'),
                    allowResize: true,
                    width: '100px',
                },
                {
                    property: 'conditions',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.conditions'),
                    allowResize: true,
                    sortable: false,
                },
                {
                    property: 'actions',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.actions'),
                    allowResize: true,
                    sortable: false,
                },
                {
                    property: 'salesChannel.name',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.salesChannel'),
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: this.$tc('frosh-abandoned-carts.automations.columns.createdAt'),
                    allowResize: true,
                },
            ];
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('salesChannel');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

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
                    Shopware.Context.api,
                );
            } catch (error) {
                console.error('Failed to load automations:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async onToggleActive(item) {
            item.active = !item.active;

            try {
                await this.automationRepository.save(item, Shopware.Context.api);
            } catch (error) {
                console.error('Failed to update automation:', error);
                item.active = !item.active;
            }
        },

        async onDelete(item) {
            try {
                await this.automationRepository.delete(item.id, Shopware.Context.api);
                await this.loadAutomations();
            } catch (error) {
                console.error('Failed to delete automation:', error);
            }
        },

        getConditionsSummary(item) {
            if (!item.conditions || item.conditions.length === 0) {
                return '-';
            }

            const conditionLabels = {
                cart_age: this.$tc('frosh-abandoned-carts.automations.conditions.cart_age'),
                cart_value: this.$tc('frosh-abandoned-carts.automations.conditions.cart_value'),
                automation_count: this.$tc('frosh-abandoned-carts.automations.conditions.automation_count'),
                time_since_last_automation: this.$tc('frosh-abandoned-carts.automations.conditions.time_since_last_automation'),
                customer_tag: this.$tc('frosh-abandoned-carts.automations.conditions.customer_tag'),
                line_item_count: this.$tc('frosh-abandoned-carts.automations.conditions.line_item_count'),
            };

            return item.conditions
                .map((c) => conditionLabels[c.type] || c.type)
                .join(', ');
        },

        getActionsSummary(item) {
            if (!item.actions || item.actions.length === 0) {
                return '-';
            }

            const actionLabels = {
                send_email: this.$tc('frosh-abandoned-carts.automations.actions.send_email'),
                generate_voucher: this.$tc('frosh-abandoned-carts.automations.actions.generate_voucher'),
                add_customer_tag: this.$tc('frosh-abandoned-carts.automations.actions.add_customer_tag'),
                remove_customer_tag: this.$tc('frosh-abandoned-carts.automations.actions.remove_customer_tag'),
                set_customer_custom_field: this.$tc('frosh-abandoned-carts.automations.actions.set_customer_custom_field'),
            };

            return item.actions
                .map((a) => actionLabels[a.type] || a.type)
                .join(', ');
        },
    },
});
