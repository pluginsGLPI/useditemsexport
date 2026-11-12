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
use Document;
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

    public function testShowForUserDisplaysOnePage(): void
    {
        $this->login();

        $user   = $this->createItem(User::class, ['name' => __FUNCTION__]);
        $author = $this->createItem(User::class, ['name' => __FUNCTION__ . '_author']);
        $links = [];
        foreach (['0001-2026', '0002-2026', '0003-2026'] as $num => $refnumber) {
            $document = $this->createItem(Document::class, [
                'name'        => 'doc_' . $refnumber,
                'entities_id' => $this->getTestRootEntity(true),
            ]);
            // the link of a document points to its id
            $links[$refnumber] = $document->getDownloadLink();
            $this->assertStringContainsString('docid=' . $document->getID(), $links[$refnumber]);
            $this->createItem(PluginUseditemsexportExport::class, [
                'users_id'     => $user->getID(),
                'num'          => $num + 1,
                'refnumber'    => $refnumber,
                'authors_id'   => $author->getID(),
                'documents_id' => $document->getID(),
            ]);
        }

        $_SESSION['glpilist_limit'] = 2;
        $render = static function (int $start) use ($user): string {
            $_GET['start'] = $start;
            ob_start();
            (new PluginUseditemsexportExport())->showForUser($user);

            return (string) ob_get_clean();
        };

        // First page: the two first exports, with their author and document
        $first_page = $render(0);
        $this->assertStringContainsString('0001-2026', $first_page);
        $this->assertStringContainsString('0002-2026', $first_page);
        $this->assertStringNotContainsString('0003-2026', $first_page);
        $this->assertStringContainsString(__FUNCTION__ . '_author', $first_page);
        $this->assertStringContainsString($links['0001-2026'], $first_page);
        $this->assertStringContainsString($links['0002-2026'], $first_page);

        // Second page: the last export only
        $second_page = $render(2);
        $this->assertStringNotContainsString('0002-2026', $second_page);
        $this->assertStringContainsString('0003-2026', $second_page);
        $this->assertStringContainsString($links['0003-2026'], $second_page);

        unset($_GET['start']);
    }

    public function testNextRefnumberUsesGivenNum(): void
    {
        $year = date('Y');
        $this->assertSame('0042-' . $year, PluginUseditemsexportExport::getNextRefnumber(42));
        $this->assertSame(
            str_pad((string) PluginUseditemsexportExport::getNextNum(), 4, '0', STR_PAD_LEFT) . '-' . $year,
            PluginUseditemsexportExport::getNextRefnumber(),
        );
    }
}
