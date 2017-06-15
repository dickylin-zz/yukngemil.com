<?php
/**
 *   This file is part of Mobile Assistant Connector.
 *
 *   Mobile Assistant Connector is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   Mobile Assistant Connector is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with Mobile Assistant Connector. If not, see <http://www.gnu.org/licenses/>.
 */

class EmagiconeMobileassistantEventHelper
{
    private static function getModelEvent($controller, $route, $area)
    {
        if (EmagiconeMobileassistantConstants::AREA_FRONTEND == $area) {
            return null;
        }

        try {
            $controller->load->model($route);
            $modelEvent = $controller->{'model_' . str_replace('/', '_', $route)};
        } catch (Exception $e) {
            $modelEvent = null;
        }

        return $modelEvent;
    }

    private static function prepareEventsCart23x($controller, $area) {
        self::deleteEventsCart22Down($controller->db);
        self::deleteEventsCart22x($controller->db);

        self::addEvents(
            self::getModelEvent($controller, 'extension/event', $area),
            $controller->db,
            array(
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrder/after',
                    'action'  => 'extension/module/mobileassistantconnector/push_new_order_23x'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/before',
                    'action'  => 'extension/module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/after',
                    'action'  => 'extension/module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/before',
                    'action'  => 'extension/module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/after',
                    'action'  => 'extension/module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'catalog/model/account/customer/addCustomer/after',
                    'action'  => 'extension/module/mobileassistantconnector/push_new_customer_23x'
                ),
            )
        );
    }

    private static function prepareEventsCart22x($controller, $area) {
        self::deleteEventsCart22Down($controller->db);

        self::addEvents(
            self::getModelEvent($controller, 'extension/event', $area),
            $controller->db,
            array(
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrder/after',
                    'action'  => 'module/mobileassistantconnector/push_new_order'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/before',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/after',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/before',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/after',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'catalog/model/account/customer/addCustomer/after',
                    'action'  => 'module/mobileassistantconnector/push_new_customer'
                ),
            )
        );
    }

    private static function prepareEventsCart22Down($controller, $area) {
        self::addEvents(
            self::getModelEvent(
                $controller,
                version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.0.1.0', '>=')
                    ? 'extension/event'
                    : 'tool/event',
                $area
            ),
            $controller->db,
            array(
                array(
                    'trigger' => 'post.order.add',
                    'action'  => 'module/mobileassistantconnector/push_new_order'
                ),
                array(
                    'trigger' => 'pre.order.history.add',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'post.order.history.add',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'post.customer.add',
                    'action'  => 'module/mobileassistantconnector/push_new_customer'
                ),
            )
        );
    }

    private static function addEvents($modelEvent, $db, $events) {
        $count = count($events);

        for ($i = 0; $i < $count; $i++) {
            $event = self::getEvent($modelEvent, $db, $events[$i]['trigger'], $events[$i]['action']);

            if (!empty($event)) {
                continue;
            }

            if (null !== $modelEvent) {
                $modelEvent->addEvent('mobileassistantconnector', $events[$i]['trigger'], $events[$i]['action']);
            } else {
                self::addEvent($db, 'mobileassistantconnector', $events[$i]['trigger'], $events[$i]['action']);
            }
        }
    }

    private static function getEvent($modelEvent, $db, $trigger, $action) {
        if (null !== $modelEvent && version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.3.0.0', '>=')) {
            return $modelEvent->getEvent('mobileassistantconnector', $trigger, $action);
        }

        $event = $db->query(
            'SELECT `code` FROM `' . DB_PREFIX . "event` WHERE `code` = 'mobileassistantconnector' AND `trigger` = '"
            . $db->escape($trigger) . "' AND `action` = '" . $db->escape($action) . "'"
        );

		return $event->rows;
    }

    private static function addEvent($db, $code, $trigger, $action) {
        $sql = 'INSERT INTO ' . DB_PREFIX . "event SET `code` = '" . $db->escape($code) . "', `trigger` = '"
            . $db->escape($trigger) . "', `action` = '" . $db->escape($action) . "'";

        if (version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.3.0.0', '>=')) {
            $sql .= ', `status` = 1, `date_added` = now()';
        }

        $db->query($sql);
    }

    private static function deleteEventsCart22x($db) {
        self::deleteEvents(
            $db,
            array(
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrder/after',
                    'action'  => 'module/mobileassistantconnector/push_new_order'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/before',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'catalog/model/checkout/order/addOrderHistory/after',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/before',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'admin/controller/sale/order/history/after',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'catalog/model/account/customer/addCustomer/after',
                    'action'  => 'module/mobileassistantconnector/push_new_customer'
                )
            )
        );
    }

    private static function deleteEventsCart22Down($db) {
        self::deleteEvents(
            $db,
            array(
                array(
                    'trigger' => 'post.order.add',
                    'action'  => 'module/mobileassistantconnector/push_new_order'
                ),
                array(
                    'trigger' => 'pre.order.history.add',
                    'action'  => 'module/mobileassistantconnector/push_change_status_pre'
                ),
                array(
                    'trigger' => 'post.order.history.add',
                    'action'  => 'module/mobileassistantconnector/push_change_status'
                ),
                array(
                    'trigger' => 'post.customer.add',
                    'action'  => 'module/mobileassistantconnector/push_new_customer'
                ),
            )
        );
    }

    /**
     * Deletes old events after shopping cart updating
     *
     * @param $db
     * @param $events
     */
    private static function deleteEvents($db, $events) {
        $count = count($events);

        for ($i = 0; $i < $count; $i++) {
            $db->query(
                'DELETE FROM ' . DB_PREFIX . "event WHERE `code` = 'mobileassistantconnector' AND `trigger` = '"
                . $db->escape($events[$i]['trigger']) . "' AND `action` = '"
                . $db->escape($events[$i]['action']) . "'"
            );
        }
    }

    public static function checkAndAddEvents($controller, $area) {
        if (!EmagiconeMobileassistantHelper::isCartVersion20()) {
            return;
        }

        $cartVersion = EmagiconeMobileassistantHelper::getCartVersion();

        if (version_compare($cartVersion, '2.3.0.0', '>=')) {
            self::prepareEventsCart23x($controller, $area);
        } elseif (version_compare($cartVersion, '2.2.0.0', '>=')) {
            self::prepareEventsCart22x($controller, $area);
        } else {
            self::prepareEventsCart22Down($controller, $area);
        }
    }
}
