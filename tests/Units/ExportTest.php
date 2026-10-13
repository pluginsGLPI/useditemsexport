<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExport plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of UsedItemsExport.
 *
 * UsedItemsExport is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * UsedItemsExport is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UsedItemsExport. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @author    François Legastelois
 * @copyright Copyright (C) 2016-2022 by UsedItemsExport plugin team.
 * @license   AGPLv3 https://www.gnu.org/licenses/agpl-3.0.html
 * @link      https://github.com/pluginsGLPI/useditemsexport
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Useditemsexport\Tests\Units;

use Computer;
use Entity;
use Glpi\Tests\DbTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginUseditemsexportExport;
use Profile;
use Profile_User;
use Session;
use User;

final class ExportTest extends DbTestCase
{
    public function testGetAllUsedItemsForUserWithCustomAssets(): void
    {
        $definition_1 = $this->initAssetDefinition();
        $definition_2 = $this->initAssetDefinition();
        $definition_3 = $this->initAssetDefinition();

        // Login after definitions creation to get rights on custom assets
        $this->login();
        $class_1 = $definition_1->getAssetClassName();
        $class_2 = $definition_2->getAssetClassName();
        $class_3 = $definition_3->getAssetClassName();

        $entities_id = $this->getTestRootEntity(true);
        $user = $this->createItem(User::class, ['name' => __FUNCTION__]);
        $other_user = $this->createItem(User::class, ['name' => __FUNCTION__ . '_other']);

        $this->createItem(Computer::class, ['name' => 'computer', 'entities_id' => $entities_id, 'users_id' => $user->getID()]);
        $this->createItem($class_1, ['name' => 'asset_1', 'entities_id' => $entities_id, 'users_id' => $user->getID()]);
        $this->createItem($class_1, ['name' => 'asset_1_deleted', 'entities_id' => $entities_id, 'users_id' => $user->getID(), 'is_deleted' => 1]);
        $this->createItem(
            $class_1,
            ['name' => 'asset_1_template', 'template_name' => 'template', 'entities_id' => $entities_id, 'users_id' => $user->getID(), 'is_template' => 1],
        );
        $this->createItem($class_2, ['name' => 'asset_2', 'entities_id' => $entities_id, 'users_id' => $user->getID()]);
        $this->createItem($class_3, ['name' => 'asset_3_other_user', 'entities_id' => $entities_id, 'users_id' => $other_user->getID()]);

        $items = PluginUseditemsexportExport::getAllUsedItemsForUser($user->getID());

        // Each custom asset must only be listed once, under its own definition
        $this->assertSame(['computer'], array_column($items[Computer::class], 'name'));
        $this->assertSame(['asset_1'], array_column($items[$class_1], 'name'));
        $this->assertSame(['asset_2'], array_column($items[$class_2], 'name'));
        $this->assertArrayNotHasKey($class_3, $items);
    }

    public static function generatePDFEntityProvider(): iterable
    {
        // `root` is the GLPI root entity, `A` and `B` are children of the test root entity
        yield 'no default entity: first user entity visible in session' => [
            'default_entity' => null,
            'profiles'       => ['A', 'B'],
            'active_entity'  => 'B',
            'expected'       => 'B',
        ];
        yield 'default entity is root' => [
            'default_entity' => 'root',
            'profiles'       => ['root'],
            'active_entity'  => 'root',
            'expected'       => 'root',
        ];
        yield 'default entity is A' => [
            'default_entity' => 'A',
            'profiles'       => ['A', 'B'],
            'active_entity'  => 'root',
            'expected'       => 'A',
        ];
    }

    #[DataProvider('generatePDFEntityProvider')]
    public function testGeneratePDFEntity(
        ?string $default_entity,
        array $profiles,
        string $active_entity,
        string $expected,
    ): void {
        global $DB;

        $this->login();

        // Address fields are printed in the PDF header
        $address = ['address' => 'address', 'postcode' => 'postcode', 'town' => 'town', 'country' => 'country'];
        $entities = ['root' => 0];
        $this->updateItem(Entity::class, 0, $address);
        $entities['A'] = $this->createItem(Entity::class, ['name' => 'A', 'entities_id' => $this->getTestRootEntity(true)] + $address)->getID();
        $entities['B'] = $this->createItem(Entity::class, ['name' => 'B', 'entities_id' => $this->getTestRootEntity(true)] + $address)->getID();

        $user = $this->createItem(User::class, ['name' => __FUNCTION__]);
        // Replace the default profile added on user creation
        $DB->delete(Profile_User::getTable(), ['users_id' => $user->getID()]);
        foreach ($profiles as $entity) {
            $this->createItem(Profile_User::class, [
                'users_id'     => $user->getID(),
                'profiles_id'  => getItemByTypeName(Profile::class, 'Self-Service', true),
                'entities_id'  => $entities[$entity],
                'is_recursive' => 1,
            ]);
        }
        $DB->update(
            User::getTable(),
            ['entities_id' => $default_entity === null ? null : $entities[$default_entity]],
            ['id' => $user->getID()],
        );

        $this->assertTrue(Session::changeActiveEntities($entities[$active_entity], true));

        $this->assertTrue(PluginUseditemsexportExport::generatePDF($user->getID()));

        $exports = PluginUseditemsexportExport::getAllForUser($user->getID());
        $this->assertCount(1, $exports);
        $export = reset($exports);
        $this->assertSame($entities[$expected], $export['entities_id']);

        // The export must remain purgeable by the user who generated it
        $this->assertTrue((new PluginUseditemsexportExport())->can($export['id'], PURGE));
    }
}
