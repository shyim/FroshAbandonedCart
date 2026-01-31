import template from './frosh-abandoned-carts-automation-detail.html.twig';
import './frosh-abandoned-carts-automation-detail.scss';

const { Criteria } = Shopware.Data;

Shopware.Component.register('frosh-abandoned-carts-automation-detail', {
    template,

    inject: ['repositoryFactory', 'froshAbandonedCartAutomationApiService'],

    props: {
        automationId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            automation: null,
            isLoading: false,
            isSaveSuccessful: false,
            testResults: null,
            isTestLoading: false,
            showTestModal: false,
        };
    },

    computed: {
        automationRepository() {
            return this.repositoryFactory.create('frosh_abandoned_cart_automation');
        },

        isCreateMode() {
            return !this.automationId;
        },

        criteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salesChannel');

            return criteria;
        },

        conditionTypeOptions() {
            return [
                { id: 'cart_age', value: 'cart_age', label: this.$tc('frosh-abandoned-carts.automations.conditions.cart_age') },
                { id: 'cart_value', value: 'cart_value', label: this.$tc('frosh-abandoned-carts.automations.conditions.cart_value') },
                { id: 'automation_count', value: 'automation_count', label: this.$tc('frosh-abandoned-carts.automations.conditions.automation_count') },
                { id: 'time_since_last_automation', value: 'time_since_last_automation', label: this.$tc('frosh-abandoned-carts.automations.conditions.time_since_last_automation') },
                { id: 'customer_tag', value: 'customer_tag', label: this.$tc('frosh-abandoned-carts.automations.conditions.customer_tag') },
                { id: 'line_item_count', value: 'line_item_count', label: this.$tc('frosh-abandoned-carts.automations.conditions.line_item_count') },
            ];
        },

        actionTypeOptions() {
            return [
                { id: 'send_email', value: 'send_email', label: this.$tc('frosh-abandoned-carts.automations.actions.send_email') },
                { id: 'generate_voucher', value: 'generate_voucher', label: this.$tc('frosh-abandoned-carts.automations.actions.generate_voucher') },
                { id: 'add_customer_tag', value: 'add_customer_tag', label: this.$tc('frosh-abandoned-carts.automations.actions.add_customer_tag') },
                { id: 'remove_customer_tag', value: 'remove_customer_tag', label: this.$tc('frosh-abandoned-carts.automations.actions.remove_customer_tag') },
                { id: 'set_customer_custom_field', value: 'set_customer_custom_field', label: this.$tc('frosh-abandoned-carts.automations.actions.set_customer_custom_field') },
            ];
        },

        operatorOptions() {
            return [
                { id: 'gt', value: 'gt', label: '>' },
                { id: 'gte', value: 'gte', label: '>=' },
                { id: 'lt', value: 'lt', label: '<' },
                { id: 'lte', value: 'lte', label: '<=' },
                { id: 'eq', value: 'eq', label: '=' },
                { id: 'neq', value: 'neq', label: '!=' },
            ];
        },

        unitOptions() {
            return [
                { id: 'hours', value: 'hours', label: this.$tc('frosh-abandoned-carts.automations.detail.hours') },
                { id: 'days', value: 'days', label: this.$tc('frosh-abandoned-carts.automations.detail.days') },
            ];
        },
    },

    created() {
        if (this.isCreateMode) {
            this.createAutomation();
        } else {
            this.loadAutomation();
        }
    },

    methods: {
        createAutomation() {
            this.automation = this.automationRepository.create(Shopware.Context.api);
            this.automation.active = false;
            this.automation.priority = 1;
            this.automation.conditions = [];
            this.automation.actions = [];
        },

        async loadAutomation() {
            this.isLoading = true;

            try {
                this.automation = await this.automationRepository.get(
                    this.automationId,
                    Shopware.Context.api,
                    this.criteria,
                );

                // Ensure conditions is always an array
                if (!Array.isArray(this.automation.conditions)) {
                    this.automation.conditions = [];
                }
                // Ensure actions is always an array
                if (!Array.isArray(this.automation.actions)) {
                    this.automation.actions = [];
                }
            } catch (error) {
                console.error('Failed to load automation:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            try {
                await this.automationRepository.save(this.automation, Shopware.Context.api);
                this.isSaveSuccessful = true;

                if (this.isCreateMode) {
                    this.$router.push({
                        name: 'frosh.abandoned.carts.automation.detail',
                        params: { id: this.automation.id },
                    });
                }
            } catch (error) {
                console.error('Failed to save automation:', error);
            } finally {
                this.isLoading = false;
            }
        },

        addCondition() {
            if (!Array.isArray(this.automation.conditions)) {
                this.automation.conditions = [];
            }
            this.automation.conditions.push({
                type: 'cart_age',
                operator: 'gte',
                value: 24,
                unit: 'hours',
                negate: false,
            });
        },

        removeCondition(index) {
            if (Array.isArray(this.automation.conditions)) {
                this.automation.conditions.splice(index, 1);
            }
        },

        addAction() {
            if (!Array.isArray(this.automation.actions)) {
                this.automation.actions = [];
            }
            this.automation.actions.push({
                type: 'send_email',
                mailTemplateId: null,
                promotionId: null,
                codePattern: 'CART-%s%s%d%d',
                tagId: null,
                customFieldName: '',
                value: '',
            });
        },

        removeAction(index) {
            if (Array.isArray(this.automation.actions)) {
                this.automation.actions.splice(index, 1);
            }
        },

        getConditionTypeLabel(type) {
            const found = this.conditionTypeOptions.find((c) => c.value === type);
            return found ? found.label : type;
        },

        getActionTypeLabel(type) {
            const found = this.actionTypeOptions.find((a) => a.value === type);
            return found ? found.label : type;
        },

        needsValueInput(conditionType) {
            return ['cart_age', 'cart_value', 'automation_count', 'time_since_last_automation', 'line_item_count'].includes(conditionType);
        },

        needsUnitInput(conditionType) {
            return ['cart_age', 'time_since_last_automation'].includes(conditionType);
        },

        needsTagInput(conditionType) {
            return conditionType === 'customer_tag';
        },

        needsMailTemplateInput(actionType) {
            return actionType === 'send_email';
        },

        needsPromotionInput(actionType) {
            return actionType === 'generate_voucher';
        },

        needsTagActionInput(actionType) {
            return ['add_customer_tag', 'remove_customer_tag'].includes(actionType);
        },

        needsCustomFieldInput(actionType) {
            return actionType === 'set_customer_custom_field';
        },

        async testAutomation() {
            this.isTestLoading = true;
            this.testResults = null;

            try {
                this.testResults = await this.froshAbandonedCartAutomationApiService.testAutomation(
                    this.automation.conditions || [],
                    this.automation.salesChannelId,
                );
                this.showTestModal = true;
            } catch (error) {
                console.error('Failed to test automation:', error);
            } finally {
                this.isTestLoading = false;
            }
        },

        closeTestModal() {
            this.showTestModal = false;
            this.testResults = null;
        },

        formatPrice(price, currencyIsoCode) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currencyIsoCode || 'EUR',
            }).format(price);
        },
    },
});
