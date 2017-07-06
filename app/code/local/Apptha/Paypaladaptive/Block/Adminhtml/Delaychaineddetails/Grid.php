<?php

/**
 * In this class contains delayed chained grid function.
 *
 * @package         Apptha PayPal Adaptive
 * @version         0.1.1
 * @since           Magento 1.5
 * @author          Apptha Team
 * @copyright       Copyright (C) 2014 Powered by Apptha
 * @license         http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @Creation Date   March 26,2014
 * @Modified By     Ramkumar M
 * @Modified Date   March 26,2014
 *
 * */
class Apptha_Paypaladaptive_Block_Adminhtml_Delaychaineddetails_Grid extends Mage_Adminhtml_Block_Widget_Grid {
    /*
     * Class constructor
     */

    public function __construct() {
        parent::__construct();
        $this->setId('delaychaineddetailsGrid');
        $this->setDefaultSort('increment_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /*
     * Prepare payment grid collection 
     * 
     * @return object collection
     */

    protected function _prepareCollection() {
        $collections = Mage::getModel('paypaladaptive/delaychaineddetails')->getCollection()
                ->addFieldToFilter('transaction_status', 'Completed')
                ->addFieldToFilter('is_paid', 0);
        $this->setCollection($collections);
        return parent::_prepareCollection();
    }

    /*
     * Preparing payment grid columns 
     * 
     * @return object collection
     */

    protected function _prepareColumns() {

        $this->addColumn('increment_id', array(
            'header' => Mage::helper('paypaladaptive')->__('Increment Id'),
            'width' => '20px',
            'index' => 'increment_id',
            'type' => 'number',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('paypaladaptive')->__('Created At'),
            'width' => '20px',
            'index' => 'created_at',
        ));

        $this->addColumn('receiver_id', array(
            'header' => Mage::helper('paypaladaptive')->__('Receiver Id'),
            'width' => '20px',
            'index' => 'receiver_id',
        ));

        $this->addColumn('receiver_amount', array(
            'header' => Mage::helper('paypaladaptive')->__('Received Amount'),
            'width' => '20px',
            'index' => 'receiver_amount',
        ));

        $this->addColumn('currency_code', array(
            'header' => Mage::helper('paypaladaptive')->__('Currency'),
            'width' => '20px',
            'index' => 'currency_code',
        ));

        $this->addColumn('buyer_paypal_mail', array(
            'header' => Mage::helper('paypaladaptive')->__('Buyer Paypal Id'),
            'width' => '30px',
            'index' => 'buyer_paypal_mail',
        ));

        $this->addColumn('receiver_transaction_id', array(
            'header' => Mage::helper('paypaladaptive')->__('Transaction Id'),
            'width' => '30px',
            'index' => 'receiver_transaction_id',
        ));

        $this->addColumn('transaction_status', array(
            'header' => Mage::helper('paypaladaptive')->__('Transaction Status'),
            'width' => '20px',
            'index' => 'transaction_status',
        ));

        $this->addColumn('order_status', array(
            'header' => Mage::helper('paypaladaptive')->__('Order Status'),
            'width' => '20px',
            'filter' => false,
            'index' => 'order_id',
            'renderer' => 'Apptha_Paypaladaptive_Block_Adminhtml_Renderersource_Orderstatus'
        ));

        $this->addColumn('payment_action', array(
            'header' => Mage::helper('paypaladaptive')->__('Pay Action'),
            'width' => '20px',
            'filter' => false,
            'index' => 'order_id',
            'renderer' => 'Apptha_Paypaladaptive_Block_Adminhtml_Renderersource_Executepayment'
        ));

        $this->addColumn('executepayment_date', array(
            'header' => Mage::helper('paypaladaptive')->__('Execute Date'),
            'width' => '20px',
            'index' => 'executepayment_date',
        ));

        $this->addColumn('execute_payment', array(
            'header' => Mage::helper('paypaladaptive')->__('Edit'),
            'align' => 'center',
            'width' => '80',
            'type' => 'action',
            'getter' => 'getOrderId',
            'actions' => array(
                array(
                    'caption' => Mage::helper('paypaladaptive')->__('Edit Execute Date'),
                    'url' => array('base' => '*/*/setexecutepaymentdate/'),
                    'field' => 'order_id',
                    'title' => Mage::helper('paypaladaptive')->__('Edit')
                ),
            ),
            'filter' => false,
            'sortable' => false,
            'index' => 'stores',
            'is_system' => true,
        ));

        $this->addColumn('view', array(
            'header' => Mage::helper('paypaladaptive')->__('Action'),
            'width' => '30',
            'type' => 'action',
            'getter' => 'getOrderId',
            'actions' => array(
                array(
                    'caption' => Mage::helper('paypaladaptive')->__('View'),
                    'url' => array('base' => 'adminhtml/sales_order/view/'),
                    'field' => 'order_id'
                )
            ),
            'filter' => false,
            'sortable' => false,
            'index' => 'stores',
            'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    /*
     * Get row url
     * 
     * @param object $row collection
     * @return bool 
     */

    public function getRowUrl($row) {
        return FALSE;
    }

}

