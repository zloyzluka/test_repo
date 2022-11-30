<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Logxstar\Integration\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        /**
         * Create table 'sales_order'
         */
         $installer->getConnection()
            ->addColumn(
                $installer->getTable('sales_order'),
                'internal_reference',
                array(
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>32,
                    'comment' => 'Logxstar internal reference'
                )
            );
        $installer->getConnection()->addColumn(
                 $installer->getTable('sales_order'),
                'logxstar_pickuppoint',
                 array(
                     'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                     'length' =>1024,
                     'comment' =>'logxstar pickuppoint'
                 )
            );
        $installer->getConnection()->addColumn(
                $installer->getTable('sales_order'),
                'logxstar_status',
                 array(
                    'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>255,
                    'comment' =>'logxstar status'
                 )
            );

        $installer->getConnection()
            ->addColumn(
                $installer->getTable('sales_shipment'),
                'logxstar_label',
                array(
                    'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>100000,
                    'comment' =>'logxstar pdf label string'
                )
            );

        $installer->getConnection()
            ->addColumn(
                $installer->getTable('sales_order_grid'),
                'logxstar_status',
                array(
                    'type' =>\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' =>255,
                    'comment' =>'logxstar shipment status'
                )
            );
        //$installer->getConnection()->query("DELETE from ".$installer->getTable('ui_bookmark')." WHERE namespace='sales_order_grid'");
        $installer->endSetup();
    }
}
