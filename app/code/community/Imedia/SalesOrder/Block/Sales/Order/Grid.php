<?php
class Imedia_SalesOrder_Block_Sales_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    protected function _prepareCollection()
	{
		$collection = Mage::getResourceModel($this->_getCollectionClass());
      
		$collection->getSelect()
					->join(array('im'=>'sales_flat_order'),'im.entity_id = main_table.entity_id',array('im.increment_id','im.store_id','im.created_at','im.customer_email','im.status','im.base_grand_total','im.grand_total'))
					->join(array('im_item'=>'sales_flat_order_item'),'im_item.order_id = main_table.entity_id',array('product_sku'=>new Zend_Db_Expr('group_concat(im_item.sku SEPARATOR ",")'),'product_name'=>new Zend_Db_Expr('group_concat(im_item.name SEPARATOR ",")')));
					
		$collection->getSelect()->group('main_table.entity_id');	
		
		$this->setCollection($collection);
		return parent::_prepareCollection();
	}
	
    protected function _prepareColumns()
    {

        $this->addColumn('real_order_id', array(
			'header'=> Mage::helper('salesorder')->__('Order #'),
			'width' => '80px',
			'type' => 'text',
			'index' => 'increment_id',
			'filter_index' => 'im.increment_id',
		));
		
		if (!Mage::app()->isSingleStoreMode()) {
			$this->addColumn('store_id', array(
				'header' => Mage::helper('salesorder')->__('Purchased From (Store)'),
				'index' => 'store_id',
				'filter_index' => 'im.store_id',
				'type' => 'store',
				'store_view'=> true,
				'display_deleted' => true,
			));
		}

		$this->addColumn('created_at', array(
			'header' => Mage::helper('salesorder')->__('Purchased On'),
			'index' => 'created_at',
			'filter_index' => 'im.created_at',
			'width'=>'100px',
			'type' => 'datetime',			
		));
		
        $this->addColumn('billing_name', array(
            'header' => Mage::helper('salesorder')->__('Bill to Name'),
            'index' => 'billing_name',
        ));

		$this->addColumn('shipping_name', array(
            'header' => Mage::helper('salesorder')->__('Ship to Name'),
            'index' => 'shipping_name',
        ));
		
		$productName = Mage::getStoreConfig('salesorder/grid_options/show_product_name',Mage::app()->getStore());
		if($productName == 1){	
			$this->addColumn('product_name', array(
				'header' => Mage::helper('salesorder')->__('Product Name'),
				'type' => 'text',
				'index' => 'product_name',
				'filter_index' => 'im_item.name',
				
			));
		}

		$productSku = Mage::getStoreConfig('salesorder/grid_options/show_product_sku',Mage::app()->getStore());
		if($productSku == 1){	
			$this->addColumn('product_sku', array(
				'header' => Mage::helper('salesorder')->__('SKU'),
				'type' => 'text',
				'index' => 'product_sku',
				'filter_index' => 'im_item.sku',
				
			));		
		}
		$emailValue = Mage::getStoreConfig('salesorder/grid_options/show_customer_email',Mage::app()->getStore());
		if($emailValue == 1){		
			$this->addColumn('customer_email', array(
				'header' => Mage::helper('salesorder')->__('Customer Email'),
				'index' => 'customer_email',
				'type' => 'text',
				'filter_index' => 'im.customer_email',
			));
		}
		
		$this->addColumn('base_grand_total', array(
            'header' => Mage::helper('salesorder')->__('G.T. (Base)'),
            'index' => 'base_grand_total',
            'filter_index' => 'im.base_grand_total',
			'type'  => 'currency',
            'currency' => 'base_currency_code',
        ));

        $this->addColumn('grand_total', array(
			'header' => Mage::helper('salesorder')->__('G.T. (Purchased)'),
			'index' => 'grand_total',
			'filter_index' => 'im.grand_total',
			'type' => 'currency',
			'currency' => 'order_currency_code',
		));


        $this->addColumn('status', array(
			'header' => Mage::helper('salesorder')->__('Status'),
			'index' => 'status',
			'filter_index' => 'im.status',
			'type' => 'options',
			'width' => '70px',
			'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
		));

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            $this->addColumn('action',
                array(
                    'header'    => Mage::helper('salesorder')->__('Action'),
                    'width'     => '50px',
                    'type'      => 'action',
                    'getter'     => 'getId',
                    'actions'   => array(
                        array(
                            'caption' => Mage::helper('salesorder')->__('View'),
                            'url'     => array('base'=>'*/sales_order/view'),
                            'field'   => 'order_id'
                        )
                    ),
                    'filter'    => false,
                    'sortable'  => false,
                    'index'     => 'stores',
                    'is_system' => true,
            ));
        }
        $this->addRssList('rss/order/new', Mage::helper('salesorder')->__('New Order RSS'));

        $this->addExportType('*/*/exportCsv', Mage::helper('salesorder')->__('CSV'));
        $this->addExportType('*/*/exportExcel', Mage::helper('salesorder')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel')) {
            $this->getMassactionBlock()->addItem('cancel_order', array(
                 'label'=> Mage::helper('salesorder')->__('Cancel'),
                 'url'  => $this->getUrl('*/sales_order/massCancel'),
            ));
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/hold')) {
            $this->getMassactionBlock()->addItem('hold_order', array(
                 'label'=> Mage::helper('salesorder')->__('Hold'),
                 'url'  => $this->getUrl('*/sales_order/massHold'),
            ));
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/unhold')) {
            $this->getMassactionBlock()->addItem('unhold_order', array(
                 'label'=> Mage::helper('salesorder')->__('Unhold'),
                 'url'  => $this->getUrl('*/sales_order/massUnhold'),
            ));
        }

        $this->getMassactionBlock()->addItem('pdfinvoices_order', array(
             'label'=> Mage::helper('salesorder')->__('Print Invoices'),
             'url'  => $this->getUrl('*/sales_order/pdfinvoices'),
        ));

        $this->getMassactionBlock()->addItem('pdfshipments_order', array(
             'label'=> Mage::helper('salesorder')->__('Print Packingslips'),
             'url'  => $this->getUrl('*/sales_order/pdfshipments'),
        ));

        $this->getMassactionBlock()->addItem('pdfcreditmemos_order', array(
             'label'=> Mage::helper('salesorder')->__('Print Credit Memos'),
             'url'  => $this->getUrl('*/sales_order/pdfcreditmemos'),
        ));

        $this->getMassactionBlock()->addItem('pdfdocs_order', array(
             'label'=> Mage::helper('salesorder')->__('Print All'),
             'url'  => $this->getUrl('*/sales_order/pdfdocs'),
        ));

        $this->getMassactionBlock()->addItem('print_shipping_label', array(
             'label'=> Mage::helper('salesorder')->__('Print Shipping Labels'),
             'url'  => $this->getUrl('*/sales_order_shipment/massPrintShippingLabel'),
        ));

        return $this;
    }

    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('*/sales_order/view', array('order_id' => $row->getId()));
        }
        return false;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }

}
