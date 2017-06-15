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

class EmagiconeMobileassistantSettingHelper
{
    private static function getGroupField()
    {
        return version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.0.1.0', '>=') ? 'code' : 'group';
    }

    private static function saveSettingValue($db, $sql, $value)
    {
        $isSerialized = false;

        if (is_array($value)) {
            $value = version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.1.0.1', '>=')
                ? json_encode($value)
                : serialize($value);

            $isSerialized = true;
        }

        $sql = sprintf($sql, $value, $isSerialized ? 1 : 0);
        $db->query($sql);
    }

    public static function getSetting($db, $group, $store_id = 0, $settingModel = null) {
        if (null !== $settingModel) {
            return $settingModel->getSetting($group, $store_id);
        }

        $data = array();

        $query = $db->query(
            'SELECT * FROM ' . DB_PREFIX . 'setting WHERE store_id = ' . (int)$store_id . ' AND `'
            . self::getGroupField() . "` = '" . $db->escape($group) . "'"
        );

        foreach ($query->rows as $result) {
            if (!$result['serialized']) {
                $data[$result['key']] = $result['value'];
            } else {
                $data[$result['key']] = version_compare(EmagiconeMobileassistantHelper::getCartVersion(), '2.0.1.0', '>=')
                    ? json_decode($result['value'], true)
                    : unserialize($result['value']);
            }
        }

        return $data;
    }

    public static function editSetting($db, $group, $data, $store_id = 0, $settingModel = null) {
        $store_id = (int) $store_id;

        if (null !== $settingModel) {
            $settingModel->editSetting($group, $data, $store_id);
            return;
        }

        self::deleteSetting($db, $group, $store_id);

        foreach ($data as $key => $value) {
            $sql = 'INSERT INTO ' . DB_PREFIX . "setting SET store_id = $store_id, `"
                    . self::getGroupField() . "` = '" . $db->escape($group) . "', `key` = '" . $db->escape($key)
                    . "', `value` = '%s', serialized = %d";

            self::saveSettingValue($db, $sql, $value);
        }
    }

    public static function deleteSetting($db, $group, $store_id = 0, $settingModel = null) {
        if (null !== $settingModel) {
            $settingModel->deleteSetting($group, $store_id);
            return;
        }

        $db->query(
            'DELETE FROM ' . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `"
            . self::getGroupField() . "` = '" . $db->escape($group) . "'"
        );
    }

    public static function editSettingValue($db, $group = '', $key = '', $value = '', $store_id = 0, $settingModel = null) {
        if (null !== $settingModel) {
            $settingModel->editSettingValue($group, $key, $value, $store_id);
            return;
        }

        $sql = 'UPDATE ' . DB_PREFIX . "setting SET `value` = '%s', serialized = %d WHERE `"
                . self::getGroupField() . "` = '" . $db->escape($group) . "' AND `key` = '" . $db->escape($key)
                . "' AND store_id = " . (int)$store_id;

        self::saveSettingValue($db, $sql, $value);
    }
}
