<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Класс для классификации строк на основе паттернов.
 *
 * Этот класс независим от Moodle и легко переносим между проектами.
 * Поддерживает три типа сравнения:
 * - точное совпадение (регистрозависимое)
 * - поиск подстроки (регистроНЕзависимый)
 * - регулярное выражение
 * 
 * // Точное совпадение
 * $tester = new pattern_tester('Категория 2024');
 * $tester->test('Категория 2024'); // true
 * $tester->test('категория 2024'); // false
 * 
 * // Подстрока (регистронезависимо)
 * $tester = new pattern_tester('*КАТЕГОРИЯ 2024*');
 * $tester->test('Категория 2024'); // true
 * $tester->test('категория 2024'); // true
 * 
 * // Regex с флагом i
 * $tester = new pattern_tester('/категория/i');
 * $tester->test('Категория'); // true
 * $tester->test('КАТЕГОРИЯ'); // true
 * 
 * // Select - фильтрация массива
 * $tester = new pattern_tester('*2024*');
 * $categories = ['Категория 2023', 'Категория 2024', 'Архив 2024'];
 * $result = $tester->select($categories);
 * // Результат: ['Категория 2024', 'Архив 2024']
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv\tools;

defined('MOODLE_INTERNAL') || die();

/**
 * Класс для тестирования строк на соответствие паттерну.
 *
 * Автоматически определяет тип паттерна:
 * - /pattern/ или /pattern/flags - регулярное выражение
 * - *substring* - поиск подстроки (регистроНЕзависимый)
 * - любая другая строка - точное совпадение (регистрозависимое)
 */
class pattern_tester {

    /**
     * Тип паттерна: точное совпадение.
     */
    const TYPE_EXACT = 'exact';

    /**
     * Тип паттерна: поиск подстроки.
     */
    const TYPE_SUBSTRING = 'substring';

    /**
     * Тип паттерна: регулярное выражение.
     */
    const TYPE_REGEX = 'regex';

    /**
     * Оригинальная строка паттерна.
     *
     * @var string
     */
    private $pattern;

    /**
     * Тип паттерна (exact, substring, regex).
     *
     * @var string
     */
    private $type;

    /**
     * Скомпилированный паттерн для использования в проверках.
     *
     * @var string
     */
    private $compiled_pattern;

    /**
     * Конструктор класса.
     *
     * Автоматически определяет тип паттерна и подготавливает его для использования.
     *
     * @param string $pattern Паттерн для сравнения
     * @throws \InvalidArgumentException Если паттерн пустой или невалидный
     */
    public function __construct(string $pattern) {
        if (empty($pattern)) {
            throw new \InvalidArgumentException('Паттерн не может быть пустым.');
        }

        $this->pattern = $pattern;
        $this->detect_and_compile_pattern();
    }

    /**
     * Определяет тип паттерна и компилирует его для использования.
     *
     * @return void
     */
    private function detect_and_compile_pattern(): void {
        // Проверка на регулярное выражение: начинается и заканчивается на `/`.
        if (preg_match('/^\/.*\/[imsxeADSUXJu]*$/', $this->pattern)) {
            $this->type = self::TYPE_REGEX;
            $this->compiled_pattern = $this->pattern;
            
            // Проверяем валидность регулярного выражения.
            set_error_handler(function() {});
            $valid = @preg_match($this->compiled_pattern, '') !== false;
            restore_error_handler();
            
            if (!$valid) {
                throw new \InvalidArgumentException('Некорректное регулярное выражение: ' . $this->pattern);
            }
        } else if (strlen($this->pattern) >= 2 && 
                   $this->pattern[0] === '*' && 
                   $this->pattern[strlen($this->pattern) - 1] === '*') {
            // Проверка на подстроку: обрамлена звёздочками.
            $this->type = self::TYPE_SUBSTRING;
            // Убираем звёздочки с начала и конца.
            $this->compiled_pattern = mb_substr($this->pattern, 1, mb_strlen($this->pattern) - 2);
        } else {
            // Точное совпадение по умолчанию.
            $this->type = self::TYPE_EXACT;
            $this->compiled_pattern = $this->pattern;
        }
    }

    /**
     * Проверяет, соответствует ли строка паттерну.
     *
     * @param string $str Строка для проверки
     * @return bool True, если строка соответствует паттерну
     */
    public function test(string $str): bool {
        switch ($this->type) {
            case self::TYPE_EXACT:
                // Точное совпадение с учётом регистра.
                return $str === $this->compiled_pattern;

            case self::TYPE_SUBSTRING:
                // Регистронезависимый поиск подстроки.
                return mb_stripos($str, $this->compiled_pattern) !== false;

            case self::TYPE_REGEX:
                // Проверка регулярным выражением.
                return preg_match($this->compiled_pattern, $str) === 1;

            default:
                return false;
        }
    }

    /**
     * Выбирает из массива все строки, соответствующие паттерну.
     *
     * Сохраняет исходные ключи массива.
     *
     * @param array $strings Массив строк для фильтрации
     * @return array Массив строк, соответствующих паттерну
     */
    public function select(array $strings): array {
        $result = [];
        
        foreach ($strings as $key => $value) {
            // Преобразуем значение в строку, если это не строка.
            $str = is_string($value) ? $value : (string)$value;
            
            if ($this->test($str)) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Возвращает оригинальный паттерн.
     *
     * @return string Оригинальная строка паттерна
     */
    public function get_pattern(): string {
        return $this->pattern;
    }

    /**
     * Возвращает тип паттерна.
     *
     * @return string Тип паттерна (exact, substring, regex)
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Возвращает скомпилированный паттерн.
     *
     * Для отладки и диагностики.
     *
     * @return string Скомпилированный паттерн
     */
    public function get_compiled_pattern(): string {
        return $this->compiled_pattern;
    }
}

