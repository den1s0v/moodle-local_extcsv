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
 * CSV importer for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use curl;
use moodle_exception;

/**
 * CSV importer class
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_importer {

    /**
     * Download CSV/TSV content from URL
     *
     * @param string $url
     * @return string CSV/TSV content
     * @throws moodle_exception
     */
    public static function download_content($url) {
        $curl = new curl();
        $curl->setopt([
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        $content = $curl->get($url);
        $errorno = $curl->get_errno();

        if ($errorno !== 0) {
            $error = $curl->error;
            if (is_array($error)) {
                $error = implode(', ', $error);
            }
            // Ensure error is a string
            $error = (string)$error;
            if (empty($error)) {
                $error = "CURL error #{$errorno}";
            }
            throw new moodle_exception('downloaderror', 'local_extcsv', null, $error);
        }

        // Get HTTP code
        $httpcode = $curl->get_info(CURLINFO_HTTP_CODE);
        
        // Convert to integer, handling both scalar and array returns
        $httpcodeint = 0;
        if (is_numeric($httpcode)) {
            $httpcodeint = (int)$httpcode;
        } elseif (is_array($httpcode)) {
            // Try different possible array keys
            if (isset($httpcode[CURLINFO_HTTP_CODE])) {
                $httpcodeint = (int)$httpcode[CURLINFO_HTTP_CODE];
            } elseif (isset($httpcode['http_code'])) {
                $httpcodeint = (int)$httpcode['http_code'];
            } elseif (isset($httpcode['HTTP_CODE'])) {
                $httpcodeint = (int)$httpcode['HTTP_CODE'];
            }
        }
        
        if ($httpcodeint === 0 || $httpcodeint < 200 || $httpcodeint >= 300) {
            // Build error message - ensure it's always a string
            if ($httpcodeint === 0) {
                $errormsg = "Не удалось получить HTTP код ответа";
            } else {
                $errormsg = (string)$httpcodeint;
            }
            
            throw new moodle_exception('downloadhttperror', 'local_extcsv', null, $errormsg);
        }

        if (empty($content)) {
            throw new moodle_exception('downloadempty', 'local_extcsv');
        }

        return $content;
    }

    /**
     * Parse CSV/TSV content from string
     *
     * @param string $content CSV/TSV content
     * @param string $contenttype 'csv' or 'tsv'
     * @return array Array of rows, each row is an array of values
     */
    public static function parse_content($content, $contenttype = 'csv') {
        $delimiter = $contenttype === 'tsv' ? "\t" : ',';

        // Use PHP memory stream to parse CSV without writing to disk
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Get preview of CSV (first few rows and column names)
     *
     * @param string $content CSV/TSV content
     * @param string $contenttype 'csv' or 'tsv'
     * @param int $maxrows Maximum number of rows to preview
     * @return array ['headers' => [...], 'rows' => [[...], ...]]
     */
    public static function get_preview($content, $contenttype = 'csv', $maxrows = 10) {
        $allrows = self::parse_content($content, $contenttype);

        if (empty($allrows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_shift($allrows);
        $rows = array_slice($allrows, 0, $maxrows);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Extract Google Sheets ID and GID from URL
     *
     * @param string $url Google Sheets URL
     * @return array ['id' => string, 'gid' => string|null]
     */
    public static function parse_google_sheets_url($url) {
        $result = ['id' => null, 'gid' => null];

        // Extract spreadsheet ID from URL
        // Format: https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit#gid=GID
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $result['id'] = $matches[1];
        }

        // Extract GID from URL
        if (preg_match('/[#&]gid=([0-9]+)/', $url, $matches)) {
            $result['gid'] = $matches[1];
        }

        return $result;
    }

    /**
     * Build Google Sheets export URL
     *
     * @param string $spreadsheetid Spreadsheet ID
     * @param string|null $gid Sheet GID (optional)
     * @param string $format Export format (csv or tsv)
     * @return string Export URL
     */
    public static function build_google_sheets_url($spreadsheetid, $gid = null, $format = 'csv') {
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetid}/export?format={$format}";
        if ($gid !== null) {
            $url .= "&gid={$gid}";
        }
        return $url;
    }

    /**
     * Process Google Sheets URL: convert to export URL if needed
     *
     * @param string $url Google Sheets URL (may be edit/view URL or export URL)
     * @param string $contenttype Desired content type (csv or tsv)
     * @return string Export URL ready for download
     */
    public static function process_google_sheets_url($url, $contenttype = 'csv') {
        // If it's already an export URL, return as is
        if (strpos($url, '/export?format=') !== false) {
            return $url;
        }

        // Parse the URL to extract ID and GID
        $parsed = self::parse_google_sheets_url($url);
        if (!$parsed['id']) {
            // Not a Google Sheets URL, return as is
            return $url;
        }

        // Build export URL
        return self::build_google_sheets_url($parsed['id'], $parsed['gid'], $contenttype);
    }

    /**
     * Download and parse CSV/TSV from source
     *
     * @param \local_extcsv\model\source_model $source
     * @return array Array of rows
     * @throws moodle_exception
     */
    public static function import_from_source($source) {
        $url = $source->get('url');
        $contenttype = $source->get('content_type');

        // Process Google Sheets URL if needed
        $exporturl = self::process_google_sheets_url($url, $contenttype);

        // Download content
        $content = self::download_content($exporturl);

        // Parse content
        return self::parse_content($content, $contenttype);
    }
}

