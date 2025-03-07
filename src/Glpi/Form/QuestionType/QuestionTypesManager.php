<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
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

namespace Glpi\Form\QuestionType;

use DirectoryIterator;
use ReflectionClass;

/**
 * Helper class to load all available question types
 */
final class QuestionTypesManager
{
    /**
     * Singleton instance
     * @var QuestionTypesManager|null
     */
    protected static ?QuestionTypesManager $instance = null;

    /**
     * Available question types
     * @var QuestionTypeInterface[]
     */
    protected array $question_types = [];

    /**
     * Private constructor to prevent instantiation (singleton)
     */
    private function __construct()
    {
        self::loadQuestionsTypes();
    }

    /**
     * Get the singleton instance
     *
     * @return QuestionTypesManager
     */
    public static function getInstance(): QuestionTypesManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the default question type class
     *
     * @return string
     */
    public function getDefaultTypeClass(): string
    {
        return QuestionTypeShortText::class;
    }

    /**
     * Get all available question categories
     *
     * @return iterable<QuestionTypeCategory>
     */
    public function getCategories(): iterable
    {
        return QuestionTypeCategory::cases();
    }

    /**
     * Get all available question categories
     *
     * @return QuestionTypeInterface[]
     */
    public function getQuestionTypes(): array
    {
        return $this->question_types;
    }

    /**
     * Get available types for a given parent category
     *
     * @param QuestionTypeCategory $category Parent category
     *
     * @return QuestionTypeInterface[]
     */
    public function getTypesForCategory(QuestionTypeCategory $category): array
    {
        $filtered_types = array_filter(
            $this->question_types,
            fn(QuestionTypeInterface $type) => $type->getCategory() === $category
        );

        uasort(
            $filtered_types,
            fn(QuestionTypeInterface $a, QuestionTypeInterface $b) => $a->getWeight() <=> $b->getWeight()
        );

        return $filtered_types;
    }

    /**
     * Automatically build core questions type list.
     *
     * TODO: Would be better to do it with a DI auto-discovery feature, but
     * it is not possible yet.
     *
     * @return void
     */
    protected function loadQuestionsTypes(): void
    {
        // Get files in the current directory
        $directory_iterator = new DirectoryIterator(__DIR__);

        while ($directory_iterator->valid()) {
            // Compute class name with the expected namespace
            $classname = $directory_iterator->getExtension() === 'php'
                ? 'Glpi\\Form\\QuestionType\\' . $directory_iterator->getBasename('.php')
                : null;

            // Validate that the class is a valid question type
            if (
                $classname !== null
                && class_exists($classname)
                && is_subclass_of($classname, QuestionTypeInterface::class)
                && (new ReflectionClass($classname))->isAbstract() === false
            ) {
                $this->question_types[$classname] = new $classname();
            }

            $directory_iterator->next();
        }

        // Sort question types by weight
        uasort(
            $this->question_types,
            fn(QuestionTypeInterface $a, QuestionTypeInterface $b) => $a->getWeight() <=> $b->getWeight()
        );
    }

    public function getTemplateSelectionForCategories(): string
    {
        $icons = array_combine(
            array_map(
                fn(QuestionTypeCategory $type) => $type->value,
                (array) $this->getCategories()
            ),
            array_map(
                fn(QuestionTypeCategory $type) => $type->getIcon(),
                (array) $this->getCategories()
            )
        );
        $js_icons = json_encode($icons);

        return <<<JS
            function(item) {
                const icons = {$js_icons};
                return $('<span class="d-flex flex-row-reverse align-items-center gap-2">'
                    + '<i class="' + _.escape(icons[item.id]) + '"></i>'
                    + _.escape(item.text)
                    + '</span>');
            }
JS;
    }

    public function getTemplateResultForCategories(): string
    {
        $icons = array_combine(
            array_map(
                fn(QuestionTypeCategory $type) => $type->value,
                (array) $this->getCategories()
            ),
            array_map(
                fn(QuestionTypeCategory $type) => $type->getIcon(),
                (array) $this->getCategories()
            )
        );
        $js_icons = json_encode($icons);

        return <<<JS
            function(item) {
                const icons = {$js_icons};
                return $('<span class="d-flex align-items-center gap-2">'
                    + '<i class="' + _.escape(icons[item.id]) + '"></i>'
                    + _.escape(item.text)
                    + '</span>');
            }
JS;
    }

    public function getTemplateSelectionForQuestionTypes(): string
    {
        $icons = array_combine(
            array_map(
                fn(QuestionTypeInterface $type) => $type::class,
                $this->getQuestionTypes()
            ),
            array_map(
                fn(QuestionTypeInterface $type) => $type->getIcon(),
                $this->getQuestionTypes()
            )
        );
        $js_icons = json_encode($icons);

        return <<<JS
            function(item) {
                const icons = {$js_icons};
                return $('<span class="d-flex flex-row-reverse align-items-center gap-2">'
                    + '<i class="' + _.escape(icons[item.id]) + '"></i>'
                    + _.escape(item.text)
                    + '</span>');
            }
JS;
    }

    public function getTemplateResultForQuestionTypes(): string
    {
        $icons = array_combine(
            array_map(
                fn(QuestionTypeInterface $type) => $type::class,
                $this->getQuestionTypes()
            ),
            array_map(
                fn(QuestionTypeInterface $type) => $type->getIcon(),
                $this->getQuestionTypes()
            )
        );
        $js_icons = json_encode($icons);

        return <<<JS
            function(item) {
                const icons = {$js_icons};
                return $('<span class="d-flex align-items-center gap-1">'
                    + '<i class="' + _.escape(icons[item.id]) + '"></i>'
                    + _.escape(item.text)
                    + '</span>');
            }
JS;
    }
}
