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
 * Language strings for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Импорт внешних CSV';
$string['privacy:metadata'] = 'Плагин local_extcsv импортирует данные из внешних источников CSV/TSV.';

// Capabilities
$string['extcsv:manage_sources'] = 'Управление источниками данных CSV';

// Navigation and pages
$string['sources'] = 'Источники данных';
$string['managesources'] = 'Управление источниками CSV';

// Source fields
$string['name'] = 'Название';
$string['description'] = 'Описание';
$string['url'] = 'URL источника';
$string['content_type'] = 'Тип контента';
$string['content_type_csv'] = 'CSV';
$string['content_type_tsv'] = 'TSV';
$string['status'] = 'Состояние';
$string['status_enabled'] = 'Включено';
$string['status_disabled'] = 'Отключено';
$string['status_frozen'] = 'Заморожено';
$string['schedule'] = 'Расписание обновления';
$string['schedule_interval'] = 'Интервал обновления';
$string['schedule_cron'] = 'Cron выражение';
$string['schedule_mode'] = 'Режим расписания';
$string['schedule_mode_simple'] = 'Простой (интервал)';
$string['schedule_mode_advanced'] = 'Продвинутый (cron)';
$string['url_help'] = 'URL источника данных. Может быть прямая ссылка на CSV/TSV файл или ссылка на Google Таблицу. Для Google Таблиц будет автоматически создан URL экспорта.';
$string['schedule_cron_help'] = 'Cron выражение для автоматического обновления данных. Формат: минута час день месяц день_недели. Например: "0 2 * * *" - каждый день в 2:00. Оставьте пустым для ручного обновления.';

// Time intervals
$string['interval_minutes'] = 'минут';
$string['interval_hours'] = 'часов';
$string['interval_days'] = 'дней';
$string['every'] = 'Каждые';

// Actions
$string['addsource'] = 'Добавить источник';
$string['editsource'] = 'Редактировать источник';
$string['deletesource'] = 'Удалить источник';
$string['preview'] = 'Предпросмотр';
$string['viewdata'] = 'Просмотр данных';
$string['update'] = 'Обновить';
$string['test'] = 'Тестировать';

// Status and errors
$string['lastupdate'] = 'Последнее обновление';
$string['lastupdatestatus'] = 'Статус последнего обновления';
$string['lastupdateerror'] = 'Ошибка последнего обновления';
$string['status_success'] = 'Успешно';
$string['status_error'] = 'Ошибка';
$string['status_pending'] = 'Ожидает';

// Columns configuration
$string['columns'] = 'Колонки';
$string['column_pattern'] = 'Паттерн внешнего имени';
$string['column_shortname'] = 'Короткое внутреннее имя';
$string['column_type'] = 'Тип данных';
$string['column_type_text'] = 'Текст';
$string['column_type_int'] = 'Целое число';
$string['column_type_float'] = 'Число с точкой';
$string['column_type_bool'] = 'Логическое';
$string['column_type_date'] = 'Дата';
$string['column_type_json'] = 'JSON';
$string['selectcolumns'] = 'Выбрать колонки';

// Google Sheets
$string['google_sheets_url'] = 'URL Google Таблицы';
$string['google_sheets_id'] = 'ID таблицы';
$string['google_sheets_gid'] = 'ID листа (gid)';
$string['build_google_url'] = 'Построить URL для Google Таблицы';

// Messages
$string['sourceadded'] = 'Источник успешно добавлен';
$string['sourceupdated'] = 'Источник успешно обновлён';
$string['sourcedeleted'] = 'Источник успешно удалён';
$string['sourceupdatedsuccess'] = 'Данные источника успешно обновлены';
$string['sourceupdateerror'] = 'Ошибка при обновлении данных: {$a}';
$string['nopermission'] = 'У вас нет прав для управления источниками данных';
$string['confirmdelete'] = 'Вы уверены, что хотите удалить источник "{$a}"? Все связанные данные также будут удалены.';

// Task
$string['taskupdatesources'] = 'Обновление источников CSV';

// Errors
$string['downloaderror'] = 'Ошибка при загрузке данных: {$a}';
$string['downloadhttperror'] = 'Ошибка HTTP при загрузке: {$a}';
$string['downloadempty'] = 'Загруженный файл пуст';
$string['invalidcsvheaders'] = 'Неверные заголовки CSV';
$string['nocolumnsmapped'] = 'Не найдено соответствий для колонок';
$string['nofieldmapping'] = 'Не настроен маппинг полей';
$string['unknownfield'] = 'Неизвестное поле: {$a}';
$string['sourcenotfound'] = 'Источник не найден';
$string['nosources'] = 'Источники не найдены';
$string['invalidinterval'] = 'Неверный интервал';
$string['invalidurl'] = 'Неверный URL';
$string['column_number'] = 'Номер колонки';
$string['column_name'] = 'Имя колонки';
$string['nocolumns'] = 'Колонки не найдены';
$string['samplerows'] = 'Примеры строк';
$string['row'] = 'Строка';
$string['nodata'] = 'Данные не найдены';
$string['totalrows'] = 'Всего строк: {$a}';
$string['rows'] = 'строк';
$string['updatenow'] = 'Обновить сейчас';
$string['sourceupdatesuccess'] = 'Источник успешно обновлён. Сохранено строк: {$a}.';
$string['sourceupdateerror'] = 'Ошибка при обновлении источника: {$a}';

