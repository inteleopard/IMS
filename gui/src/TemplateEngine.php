<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2019 by Laurent Declercq <l.declercq@nuxwin.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/** @noinspection
 * PhpUnusedParameterInspection
 * PhpUnhandledExceptionInspection
 * PhpDocMissingThrowsInspection
 * PhpUnused
 */

declare(strict_types=1);

namespace iMSCP;

use iMSCP\Event\EventAggregator;
use iMSCP\Event\Events;
use iMSCP\Exception\Exception;
use Throwable;

/**
 * Class TemplateEngine
 * @package iMSCP
 */
class TemplateEngine
{
    /**
     * @var array
     */
    protected $tplName = [];

    /**
     * @var array
     */
    protected $tplData = [];

    /**
     * @var array
     */
    protected $tplOptions = [];

    /**
     * @var array
     */
    protected $dtplName = [];

    /**
     * @var array
     */
    protected $dtplData = [];

    /**
     * @var array
     */
    protected $dtplOptions = [];

    /**
     * @var array
     */
    protected $dtplValues = [];

    /**
     * @var array
     */
    protected $namespace = [];

    /**
     * @var EventAggregator
     */
    protected $eventManager;

    /**
     * Templates root directory.
     *
     * @var string
     */
    protected $rootDir = '.';

    /**
     * @var string
     */
    protected $tplStartTag = '<!-- ';

    /**
     * @var string
     */
    protected $tplEndTag = ' -->';

    /**
     * @var string
     */
    protected $tplStartTagName = 'BDP: ';

    /**
     * @var string
     */
    protected $tplEndTagName = 'EDP: ';

    /**
     * @var string
     */
    protected $tplNameRexpr = '([a-z0-9][a-z0-9\_]*)';

    /**
     * @var string
     */
    protected $tplStartRexpr;

    /**
     * @var string
     */
    protected $tplEndRexpr;

    /**
     * @var string
     */
    protected $lastParsed = '';

    /**
     * @var array
     */
    protected $stack = [];

    /**
     * @var int
     */
    protected $sp = 0;

    /**
     * @var string
     */
    protected $tplInclude = 'INCLUDE "([^\"]+)"';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->eventManager = EventAggregator::getInstance();
        $this->setRootDir(Registry::get('config')->ROOT_TEMPLATE_PATH);
        $this->tplStartRexpr = '/';
        $this->tplStartRexpr .= $this->tplStartTag;
        $this->tplStartRexpr .= $this->tplStartTagName;
        $this->tplStartRexpr .= $this->tplNameRexpr;
        $this->tplStartRexpr .= $this->tplEndTag . '/';
        $this->tplEndRexpr = '/';
        $this->tplEndRexpr .= $this->tplStartTag;
        $this->tplEndRexpr .= $this->tplEndTagName;
        $this->tplEndRexpr .= $this->tplNameRexpr;
        $this->tplEndRexpr .= $this->tplEndTag . '/';
        $this->tplInclude = '~' . $this->tplStartTag . $this->tplInclude
            . $this->tplEndTag . '~m';
    }

    /**
     * Sets templates root directory.
     *
     * @param string $rootDir
     * @return void
     */
    public function setRootDir($rootDir)
    {
        if (!is_dir($rootDir)) {
            throw new Exception(
                'TemplateEngine::setRootDir expects a valid directory.'
            );
        }

        $this->rootDir = $rootDir;
    }

    /**
     * @param string|array $namespaces Namespace(s)
     * @param string $namespacesData
     * @return void
     */
    public function assign($namespaces, $namespacesData = '')
    {
        if (is_array($namespaces)) {
            foreach ($namespaces as $name => $data) {
                $this->namespace[$name] = $data;
            }

            return;
        }

        $this->namespace[$namespaces] = $namespacesData;
    }

    /**
     *
     * @param string|array $namespaces
     * @return void
     */
    public function unsign($namespaces)
    {
        if (is_array($namespaces)) {
            foreach ($namespaces as $key => $value) {
                unset($this->namespace[$key]);
            }

            return;
        }

        unset($this->namespace[$namespaces]);
    }

    /**
     *
     * @param string|array $tName
     * @param string $tValue
     * @return void
     */
    public function define($tName, $tValue = '')
    {
        if (is_array($tName)) {
            foreach ($tName as $key => $value) {
                $this->tplName[$key] = $value;
                $this->tplData[$key] = '';
                $this->tplOptions[$key] = '';
            }

            return;
        }

        $this->tplName[$tName] = $tValue;
        $this->tplData[$tName] = '';
        $this->tplOptions[$tName] = '';
    }

    /**
     * @param string|array $tName
     * @param string $tValue
     * @return void
     */
    public function define_dynamic($tName, $tValue = '')
    {
        if (is_array($tName)) {
            foreach ($tName as $key => $value) {
                $this->dtplName[$key] = $value;
                $this->dtplData[$key] = '';
                $this->dtplOptions[$key] = '';
            }

            return;
        }

        $this->dtplName[$tName] = $tValue;
        $this->dtplData[$tName] = '';
        $this->dtplOptions[$tName] = '';
    }

    /**
     * @param string|array $tName
     * @param string $tValue
     * @return void
     */
    public function define_no_file($tName, $tValue = '')
    {
        if (is_array($tName)) {
            foreach ($tName as $key => $value) {
                $this->tplName[$key] = '_no_file_';
                $this->tplData[$key] = $value;
                $this->tplOptions[$key] = '';
            }

            return;
        }

        $this->tplName[$tName] = '_no_file_';
        $this->tplData[$tName] = $tValue;
        $this->tplOptions[$tName] = '';
    }

    /**
     * @param string|array $tName
     * @param string $tValue
     * @return void
     */
    public function define_no_file_dynamic($tName, $tValue = '')
    {
        if (is_array($tName)) {
            foreach ($tName as $key => $value) {
                $this->dtplName[$key] = '_no_file_';
                $this->dtplData[$key] = $value;
                $this->dtplOptions[$key] = '';
            }

            return;
        }

        $this->dtplName[$tName] = '_no_file_';
        $this->dtplData[$tName] = $tValue;
        $this->dtplOptions[$tName] = '';
    }

    /**
     * Checks if a namespace is defined
     *
     * @param string $namespace namespace
     * @return boolean TRUE if the namespace was define, FALSE otherwise
     */
    public function is_namespace($namespace)
    {
        return array_key_exists($namespace, $this->namespace);
    }

    /**
     * Checks if the given template is a static template
     *
     * @param string $tplName namespace
     * @return boolean TRUE if the given template is a static template, FALSE
     *                 otherwise
     */
    public function is_static_tpl($tplName)
    {
        return array_key_exists($tplName, $this->tplName);
    }

    /**
     * Checks if the given template is a dynamic template
     *
     * @param string $tplName Dynamic template name
     * @return boolean TRUE if the given template is a dynamic template, FALSE
     *                 otherwise
     */
    public function is_dynamic_tpl($tplName)
    {
        return array_key_exists($tplName, $this->dtplName);
    }

    /**
     * Parse given template namespace
     *
     * @param string $pname
     * @param string $tname
     * @throws Exception
     */
    public function parse($pname, $tname)
    {
        $this->eventManager->dispatch(Events::onParseTemplate, [
            'pname'          => $pname,
            'tname'          => $tname,
            'templateEngine' => $this
        ]);

        $addFlag = false;

        if (strpos($tname, '.') === 0) {
            $tname = substr($tname, 1);
            $addFlag = true;
        }

        if (isset($this->tplName[$tname])
            && ($this->tplName[$tname] == '_no_file_'
                || (strpos($this->dtplName[$tname], '.tpl') !== false
                    || strpos($this->dtplName[$tname], '.phtml') !== false
                )
            )
        ) {
            // static NO FILE - static FILE
            if (isset($this->tplData[$tname])
                && $this->tplData[$tname] == ''
            ) {
                $this->tplData[$tname] = $this->get_file(
                    $this->tplName[$tname]
                );
            }

            if ($addFlag) {
                if (isset($this->namespace[$pname])) {
                    $this->namespace[$pname] .= $this->substitute_dynamic(
                        $this->tplData[$tname]
                    );
                } else {
                    $this->namespace[$pname] = $this->substitute_dynamic(
                        $this->tplData[$tname]
                    );
                }
            } else {
                $this->namespace[$pname] = $this->substitute_dynamic(
                    $this->tplData[$tname]
                );
            }

            $this->lastParsed = $this->namespace[$pname];
            return;
        }

        if ($this->dtplName[$tname] == '_no_file_'
            || strpos($this->dtplName[$tname], '.tpl') !== false
            || strpos($this->dtplName[$tname], '.phtml') !== false
            || $this->find_origin($tname)
        ) {

            // dynamic NO FILE - dynamic FILE
            if (!$this->parse_dynamic($pname, $tname, $addFlag)) {
                return;
            }

            $this->lastParsed = $this->namespace[$pname];
            return;
        }

        if (!$addFlag) {
            $this->namespace[$pname] = $this->namespace[$tname];
            return;
        }

        if (isset($this->namespace[$pname])) {
            $this->namespace[$pname] .= $this->namespace[$tname];
            return;
        }

        $this->namespace[$pname] = $this->namespace[$tname];
    }

    /**
     * Load a template file
     *
     * @param string|array $fname Template file path or an array where the
     *                            second item contain the template file path
     * @return mixed|string
     */
    public function get_file($fname)
    {
        static $parentTplDir = NULL;

        if (!is_array($fname)) {
            $this->eventManager->dispatch(
                Events::onBeforeAssembleTemplateFiles,
                [
                    'context'      => $this,
                    'templatePath' => $this->rootDir . '/' . $fname
                ]
            );
        } else { // INCLUDED file
            $fname = ($parentTplDir ?: $parentTplDir) . '/' . $fname[1];
        }

        if (!$this->is_safe($fname)) {
            throw new Exception(sprintf(
                "Couldn't to find the %s template file",
                $this->rootDir . '/' . $fname
            ));
        }

        $prevParentTplDir = $parentTplDir;
        $parentTplDir = dirname($fname);
        $this->eventManager->dispatch(Events::onBeforeLoadTemplateFile, [
            'context'      => $this,
            'templatePath' => $this->rootDir . '/' . $fname
        ]);

        ob_start();
        try {
            $this->run(utils_normalizePath($this->rootDir . '/' . $fname));
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $fileContent = ob_get_clean();
        $this->eventManager->dispatch(Events::onAfterLoadTemplateFile, [
            'context'         => $this,
            'templateContent' => $fileContent
        ]);

        $fileContent = preg_replace_callback(
            $this->tplInclude, [$this, 'get_file'], $fileContent
        );
        $parentTplDir = $prevParentTplDir;
        $this->eventManager->dispatch(Events::onAfterAssembleTemplateFiles, [
            'context'         => $this,
            'templateContent' => $fileContent
        ]);

        return $fileContent;
    }

    /**
     * @param string $fname
     * @return bool
     */
    protected function is_safe($fname)
    {
        return file_exists($this->rootDir . '/' . $fname);
    }

    /**
     * Includes the template script in a scope with only public $this variables.
     *
     * @param string $scriptPath The view script to execute.
     */
    protected function run($scriptPath)
    {
        /** @noinspection PhpIncludeInspection */
        include $scriptPath;
    }

    /**
     * @param  $data
     * @return mixed
     */
    protected function substitute_dynamic($data)
    {
        if (($curlB = strpos($data, '{')) === FALSE) {
            // there is nothing to substitute in $data; return early
            return $data;
        }

        $this->sp = 0;
        $startFrom = -1;
        $this->stack[$this->sp++] = ['{', $curlB];
        $curl = $this->find_next_curl($data, $startFrom);

        while ($curl !== false) {
            if ($curl[0] == '{') {
                $this->stack[$this->sp++] = $curl;
                $startFrom = $curl[1];
            } else {
                $curlE = $curl[1];

                if ($this->sp > 0) {
                    $curl = $this->stack[--$this->sp];
                    // Check for empty stack must be done HERE !
                    $curlB = $curl[1];

                    if ($curlB < $curlE + 1) {
                        $varName = substr($data, $curlB + 1, $curlE - $curlB - 1);

                        // The whole work goes here :)
                        if (preg_match('/[A-Z0-9][A-Z0-9_]*/', $varName)) {
                            if (isset($this->namespace[$varName])) {
                                $data = substr_replace(
                                    $data,
                                    $this->namespace[$varName],
                                    $curlB,
                                    $curlE - $curlB + 1
                                );

                                // new value may also begin with '{'
                                $startFrom = $curlB - 1;
                            } elseif (isset($this->dtplData[$varName])) {
                                $data = substr_replace(
                                    $data,
                                    $this->dtplData[$varName],
                                    $curlB,
                                    $curlE - $curlB + 1
                                );

                                // new value may also begin with '{'
                                $startFrom = $curlB - 1;
                            } else {
                                // no suitable value found -> go forward
                                $startFrom = $curlB;
                            }
                        } else {
                            // go forward, we have {no variable} here.
                            $startFrom = $curlB;
                        }
                    } else {
                        // go forward, we have {} here.
                        $startFrom = $curlE;
                    }
                } else {
                    $startFrom = $curlE;
                }
            }

            $curl = $this->find_next_curl($data, $startFrom);
        }

        return $data;
    }

    /**
     * Finds the next curly bracket in the given string, starting at the given
     * position + 1
     *
     * @param string $string String in which pairs of curly bracket must be
     *                       searched
     * @param int $startPos Start search position in $string
     * @return array|bool
     */
    protected function find_next_curl($string, $startPos)
    {
        $startPos++;
        $curlStartPos = strpos($string, '{', $startPos);
        $curlEndPos = strpos($string, '}', $startPos);

        if ($curlStartPos !== false) {
            if ($curlEndPos !== false) {
                if ($curlStartPos < $curlEndPos) {
                    return ['{', $curlStartPos];
                }

                return ['}', $curlEndPos];
            }

            return ['{', $curlStartPos];
        }

        if ($curlEndPos !== false) {
            return ['}', $curlEndPos];
        }

        return false;
    }

    /**
     * @param string $tname
     * @return bool
     */
    protected function find_origin($tname)
    {
        if (!isset($this->dtplName[$tname])) {
            return false;
        }

        while (isset($this->dtplName[$tname])
            && strpos($this->dtplName[$tname], '_no_file_') === false
            && strpos($this->dtplName[$tname], '.tpl') === false
            && strpos($this->dtplName[$tname], '.phtml') === false
        ) {
            $tname = $this->dtplName[$tname];
        }

        return $tname;
    }

    /**
     * Parse dynamic template
     *
     * @param string $pname
     * @param string $tname
     * @param bool $addFlag
     * @return bool
     * @throws Exception
     */
    public function parse_dynamic($pname, $tname, $addFlag)
    {
        $child = false;
        $parent = '';

        if (isset($this->dtplName[$tname])
            && strpos($this->dtplName[$tname], '_no_file_') === false
            && strpos($this->dtplName[$tname], '.tpl') === false
            && strpos($this->dtplName[$tname], '.phtml') === false
        ) {
            $child = true;
            $parent = $this->find_origin($tname);

            if (!$parent) {
                return false;
            }
        }

        if ($child) {
            $swap = $parent;
            $parent = $tname;
            $tname = $swap;
        }

        if (empty($this->dtplData[$tname])) {
            @$this->dtplData[$tname] = $this->get_file(
                @$this->dtplName[$tname]
            );
        }

        if (!isset($this->dtplOptions[$tname])
            || strpos($this->dtplOptions[$tname], 'd_') === false
        ) {
            if (isset($this->dtplOptions[$tname])) {
                $this->dtplOptions[$tname] .= 'd_';
            } else {
                $this->dtplOptions[$tname] = 'd_';
            }

            $this->dtplData[$tname] = $this->devide_dynamic(
                isset($this->dtplData[$tname]) ? $this->dtplData[$tname] : ''
            );
        }

        if ($child) {
            $swap = $parent;
            $tname = $swap;
        }

        if ($addFlag) {
            $this->namespace[$pname] = (isset($this->namespace[$pname])
                    ? $this->namespace[$pname] : ''
                ) . $this->substitute_dynamic($this->dtplData[$tname]);

            return true;
        }

        $this->namespace[$pname] = $this->substitute_dynamic(
            $this->dtplData[$tname]
        );

        return true;
    }

    /**
     *
     * @param string $data
     * @return mixed
     */
    protected function devide_dynamic($data)
    {
        $startFrom = -1;
        $tag = $this->find_next($data, $startFrom);

        while ($tag !== false) {
            if ($tag[1] == 'b') {
                $this->stack[$this->sp++] = $tag;
                $startFrom = $tag[3];
                $tag = $this->find_next($data, $startFrom);
                continue;
            }

            $tplName = $tag[0];
            $tpl_eb_pos = $tag[2];
            $tpl_ee_pos = $tag[3];
            $tag = $this->stack [--$this->sp];
            $tpl_bb_pos = $tag[2];
            $tpl_be_pos = $tag[3];
            $this->dtplData[strtoupper($tplName)] = substr(
                $data, $tpl_be_pos + 1, $tpl_eb_pos - $tpl_be_pos - 1
            );
            $this->dtplData[$tplName] = substr(
                $data, $tpl_be_pos + 1, $tpl_eb_pos - $tpl_be_pos - 1
            );
            $data = substr_replace(
                $data,
                '{' . strtoupper($tplName) . '}',
                $tpl_bb_pos,
                $tpl_ee_pos - $tpl_bb_pos + 1
            );
            $startFrom = $tpl_bb_pos + strlen("{" . $tplName . "}") - 1;
            $tag = $this->find_next($data, $startFrom);
        }

        return $data;
    }

    /**
     * Find next dynamic block
     *
     * @param string $data Data in which search is made
     * @param int $startPos Position from which starting to search
     * @return array|bool
     */
    protected function find_next($data, $startPos)
    {
        do {
            if (false === ($tagStartPos = strpos(
                    $data, $this->tplStartTag, $startPos + 1))
            ) {
                return false;
            }

            if (false === ($tagEndPos = strpos(
                    $data, $this->tplEndTag, $tagStartPos + 1))
            ) {
                return false;
            }

            $length = $tagEndPos + strlen($this->tplEndTag) - $tagStartPos;
            $tag = substr($data, $tagStartPos, $length);

            if (!$tag) {
                return false;
            }

            if (preg_match($this->tplStartRexpr, $tag, $matches)) {
                return [
                    $matches[1],
                    'b',
                    $tagStartPos,
                    $tagEndPos + strlen($this->tplEndTag) - 1
                ];
            }

            if (preg_match($this->tplEndRexpr, $tag, $matches)) {
                return [
                    $matches[1],
                    'e',
                    $tagStartPos,
                    $tagEndPos + strlen($this->tplEndTag) - 1
                ];
            }

            $startPos = $tagEndPos;
        } while (true);

        return false;
    }

    /**
     * @param string $pname
     * @return void
     */
    public function fastPrint($pname = '')
    {
        if ($pname) {
            $this->prnt($pname);
            return;
        }

        $this->prnt();
    }

    /**
     * @param string $pname
     * @return void
     */
    public function prnt($pname = '')
    {
        if ($pname) {
            echo isset($this->namespace[$pname])
                ? $this->namespace[$pname] : '';
            return;
        }

        echo @$this->lastParsed;
    }

    /**
     * Returns last parse result
     *
     * @return string
     */
    public function getLastParseResult()
    {
        return $this->lastParsed;
    }

    /**
     * Replaces last parse result with given content
     *
     * @param string $newContent New content
     * @param string $namespace Namespace
     * @return TemplateEngine
     */
    public function replaceLastParseResult($newContent, $namespace = NULL)
    {
        $this->lastParsed = (string)$newContent;

        if (isset($this->namespace[$namespace])) {
            $this->namespace[$namespace] = $newContent;
        }

        return $this;
    }
}
