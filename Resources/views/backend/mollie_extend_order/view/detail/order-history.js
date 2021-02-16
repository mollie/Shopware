//{block name="backend/order/view/detail/order_history"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Order.view.detail.OrderHistory', {
    override: 'Shopware.apps.Order.view.detail.OrderHistory',

    getColumns:function () {
        var columns = this.callParent(arguments);

        if(columns.length > 0) {
            columns.push({
                dataIndex: 'comment',
                renderer: this.commentColumn,
                resizable: false,
                flex: 0,
                width: 50,
                fixed: true,
                align: 'center'
            });
        }

        return columns;
    },

    commentColumn: function(value) {
        if(!value || typeof value !== 'string') {
            return;
        }

        if(value.includes('Mollie')) {
            return '<img style="height:10px; width: auto;" src="{link file="backend/_resources/images/mollie.svg"}" data-qtip="' + value + '"/>';
        }
    },
});
//{/block}
