<?php 
namespace Logxstar\Integration\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
class UpgradeSchema implements  UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup,
                            ModuleContextInterface $context){
        $setup->startSetup();
        if (version_compare($context->getVersion(), '2.0.0') < 0) {

            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'logxstar_selected_date',
                 array(
                    'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>10,
                    'comment' =>'Delivery date'
                 )
            );
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order_grid'),
                'logxstar_selected_date',
                 array(
                    'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>10,
                    'comment' =>'Delivery date'
                 )
            );
            
        }

        $setup->endSetup();
    }
}