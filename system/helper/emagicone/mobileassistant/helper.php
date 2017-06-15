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

class EmagiconeMobileassistantHelper
{
    private static $cartVersion;
    private static $isCartVersion20 = null;
    private static $isCartVersion23 = null;

    private static function checkCartVersion()
    {
        if (class_exists('MijoShop')) {
            $base = MijoShop::get('base');

            $installed_ms_version = (array)$base->getMijoshopVersion();
            $mijo_version = $installed_ms_version[0];

            self::$cartVersion = version_compare($mijo_version, '3.0.0', '>=') && version_compare(VERSION, '2.0.0.0', '<')
                ? '2.0.1.0'
                : VERSION;
        } else {
            self::$cartVersion = VERSION;
        }

        self::$isCartVersion20 = version_compare(self::$cartVersion, '2.0.0.0', '>=');
        self::$isCartVersion23 = version_compare(self::$cartVersion, '2.3.0.0', '>=');
    }

    private static function updateTo1Point4Point0($db)
    {
        $query = $db->query(
            'SELECT user_id, allowed_actions FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_USERS . '`'
        );

        if (!$query->num_rows) {
            return;
        }

        $action_codes_all = array_keys(self::getActionCodes());
        $count_actions_all = count($action_codes_all);

        for ($i = 0; $i < $query->num_rows; $i++) {
            if (empty($query->rows[$i]['allowed_actions'])) {
                continue;
            }

            $user_actions = (array) json_decode($query->rows[$i]['allowed_actions']);
            $has_user_all_actions = true;
            $user_actions_codes = array_keys($user_actions);

            for ($j = 0; $j < $count_actions_all; $j++) {
                if (
                    (!in_array($action_codes_all[$j], $user_actions_codes) || $user_actions[$action_codes_all[$j]] == 0)
                    && $action_codes_all[$j] != 'order_details_products_list_pickup'
                ) {
                    $has_user_all_actions = false;
                    break;
                }
            }

            if ($has_user_all_actions) {
                $db->query(
                    'UPDATE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_USERS . "` SET allowed_actions = '"
                    . json_encode(self::getActionCodes()) . "' WHERE user_id = " . $query->rows[$i]['user_id']
                );
            }
        }
    }

    public static function getCartVersion()
    {
        if (null === self::$cartVersion) {
            self::checkCartVersion();
        }

        return self::$cartVersion;
    }

    public static function isCartVersion20()
    {
        if (null === self::$isCartVersion20) {
            self::checkCartVersion();
        }

        return self::$isCartVersion20;
    }

    public static function isCartVersion23()
    {
        if (null === self::$isCartVersion23) {
            self::checkCartVersion();
        }

        return self::$isCartVersion23;
    }

    public static function updateModule($db, $settings) {
        if (version_compare($settings['mobassist_module_version'], '1.4.0', '<')) {
            self::updateTo1Point4Point0($db);
        }

        $sql = 'SELECT user_id FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_USERS . '`';
        $query = $db->query($sql);

        if ($query->num_rows) {
            return;
        }

        $sql = 'INSERT INTO `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_USERS . "` SET
                username = '%s',
                password = '%s',
                allowed_actions = '{\"push_new_order\":\"1\",\"push_order_status_changed\":\"1\",\"push_new_customer\":\"1\",\"store_statistics\":\"1\",\"order_list\":\"1\",\"order_details\":\"1\",\"order_status_updating\":\"1\",\"order_details_products_list_pickup\":\"1\",\"customer_list\":\"1\",\"customer_details\":\"1\",\"product_list\":\"1\",\"product_details\":\"1\"}',
                qr_code_hash = '%s'";

        if (empty($settings['mobassist_login'])) {
            $settings['mobassist_login'] = 1;
        }

        if (empty($settings['mobassist_pass'])) {
            $settings['mobassist_pass'] = md5(1);
        }

        $sql = sprintf(
            $sql,
            $settings['mobassist_login'],
            $settings['mobassist_pass'],
            hash('sha256', md5(time() . rand(1111, 99999)))
        );
        $db->query($sql);

        $user_id = $db->getLastId();

        $db->query('UPDATE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_DEVICES . "` SET user_id = $user_id");
        $db->query(
            'UPDATE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
            . "` SET user_id = $user_id"
        );
    }

    public static function createTables($db) {
        $db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS . "` (
                `setting_id` int(11) NOT NULL AUTO_INCREMENT,
                `device_id` INT(10),
                `user_id` INT(10) NOT NULL,
                `registration_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                `app_connection_id` int(5) NOT NULL,
                `store_id` int(5) NOT NULL,
                `push_new_order` tinyint(1) NOT NULL DEFAULT '0',
                `push_order_statuses` text COLLATE utf8_unicode_ci NOT NULL,
                `push_new_customer` tinyint(1) NOT NULL DEFAULT '0',
                `push_currency_code` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
                `status` INT(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`setting_id`)
            )"
        );

        $column = 'device_id';
        $sql = 'SHOW COLUMNS FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
            . "` WHERE `Field` = '$column'";
        $q = $db->query($sql);
        if (!$q->num_rows) {
            $db->query(
                'ALTER TABLE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
                . "` ADD $column INT(10) NOT NULL"
            );
        }

        $column = 'user_id';
        $sql = 'SHOW COLUMNS FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
            . "` WHERE `Field` = '$column'";
        $q = $db->query($sql);
        if (!$q->num_rows) {
            $db->query(
                "ALTER TABLE `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
                . "` ADD $column INT(10) NOT NULL"
            );
        }

        $column = 'status';
        $sql = 'SHOW COLUMNS FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
            . "` WHERE `Field` = '$column'";
        $q = $db->query($sql);
        if (!$q->num_rows) {
            $db->query(
                'ALTER TABLE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_PUSH_NOTIFICATIONS
                . "` ADD $column INT(1) NOT NULL DEFAULT '1'"
            );
        }

        $db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_SESSION_KEYS . "` (
                `key_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) NOT NULL,
                `session_key` VARCHAR(100) NOT NULL,
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`key_id`)
            )"
        );

        $column = 'user_id';
        $sql = 'SHOW COLUMNS FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_SESSION_KEYS
            . "` WHERE `Field` = '$column'";
        $q = $db->query($sql);
        if (!$q->num_rows) {
            $db->query(
                'ALTER TABLE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_SESSION_KEYS
                . "` ADD $column INT(10) NOT NULL"
            );
        }

        $db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_FAILED_LOGIN . "` (
                `row_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(20) NOT NULL,
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`row_id`)
			)"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_DEVICES . "` (
                `device_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) NOT NULL,
                `device_unique_id` VARCHAR(80),
                `account_email` VARCHAR(150),
                `device_name` VARCHAR(150),
                `last_activity` DATETIME NOT NULL,
                PRIMARY KEY (`device_id`)
            )"
        );

        $column = 'user_id';
        $sql = 'SHOW COLUMNS FROM `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_DEVICES
            . "` WHERE `Field` = '$column'";
        $q = $db->query($sql);
        if (!$q->num_rows) {
            $db->query(
                'ALTER TABLE `' . DB_PREFIX . EmagiconeMobileassistantConstants::T_DEVICES
                . "` ADD $column INT(10) NOT NULL"
            );
        }

        $db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . EmagiconeMobileassistantConstants::T_USERS . "` (
                `user_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(50),
                `password` VARCHAR(50),
                `allowed_actions` text COLLATE utf8_unicode_ci NOT NULL,
                `qr_code_hash` VARCHAR(70),
                `mobassist_disable_mis_ord_notif` tinyint(1) NOT NULL DEFAULT '0',
                `user_status` tinyint(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`user_id`)
            )"
        );
    }

    public static function getActionCodes() {
        return array(
            'push_new_order' => 1,
            'push_order_status_changed' => 1,
            'push_new_customer' => 1,
            'store_statistics' => 1,
            'order_list' => 1,
            'order_details' => 1,
            'order_status_updating' => 1,
            'order_details_products_list_pickup' => 1,
            'customer_list' => 1,
            'customer_details' => 1,
            'product_list' => 1,
            'product_details' => 1
        );
    }
}
