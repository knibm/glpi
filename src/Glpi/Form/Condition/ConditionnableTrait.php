<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Form\Condition;

use Glpi\Form\Condition\VisibilityStrategy;
use JsonException;

trait ConditionnableTrait
{
    /** @return ConditionData[] */
    public function getConfiguredConditionsData(): array
    {
        parent::post_getFromDB();

        try {
            $raw_data = json_decode(
                json       : $this->fields['conditions'],
                associative: true,
                flags      : JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            $raw_data = [];
        }

        $form_data = new FormData([
            'conditions' => $raw_data,
        ]);

        return $form_data->getConditionsData();
    }

    public function getConfiguredVisibilityStrategy(): VisibilityStrategy
    {
        $strategy_value = $this->fields['visibility_strategy'] ?? "";
        $strategy = VisibilityStrategy::tryFrom($strategy_value);
        return $strategy ?? VisibilityStrategy::ALWAYS_VISIBLE;
    }
}
