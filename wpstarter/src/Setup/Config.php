<?php
/*
 * This file is part of the WPStarter package.
 *
 * (c) Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCM\WPStarter\Setup;

use WCM\WPStarter\Setup\Steps\StepInterface;

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @package WPStarter
 */
final class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    private static $defaults = [
        'gitignore'             => true,
        'env-example'           => true,
        'env-file'              => '.env',
        'move-content'          => false,
        'content-dev-op'        => 'symlink',
        'content-dev-dir'       => 'content-dev',
        'register-theme-folder' => true,
        'prevent-overwrite'     => ['.gitignore'],
        'dropins'               => [],
        'unknown-dropins'       => 'ask',
    ];

    /**
     * @var array
     */
    private static $validationMap = [
        'gitignore'             => 'validateGitignore',
        'env-example'           => 'validateBoolOrAskOrUrl',
        'env-file'              => 'validatePath',
        'register-theme-folder' => 'validateBoolOrAsk',
        'move-content'          => 'validateBoolOrAsk',
        'content-dev-dir'       => 'validatePath',
        'content-dev-op'        => 'validateContentDevOperation',
        'dropins'               => 'validatePathArray',
        'unknown-dropins'       => 'validateBoolOrAsk',
        'prevent-overwrite'     => 'validateOverwrite',
        'verbosity'             => 'validateVerbosity',
        'custom-steps'          => 'validateSteps',
        'scripts'               => 'validateScripts'
    ];

    /**
     * @var array
     */
    private $configs;

    /**
     * Constructor.
     *
     * @param array $configs
     */
    public function __construct(array $configs)
    {
        $this->configs = $this->validate($configs);
    }

    /**
     * Append-only setter.
     *
     * Allows to use config class as a DTO among steps.
     *
     * @param string $name
     * @param mixed $value
     * @param callable $validateCb
     * @return static
     */
    public function appendConfig($name, $value, callable $validateCb = null)
    {
        if ($this->offsetExists($name)) {
            throw new \BadMethodCallException(sprintf(
                "%s is append-ony: %s config is already set",
                __CLASS__,
                $name
            ));
        }

        if (!in_array($name, self::$validationMap)) {
            if (is_null($validateCb)) {
                throw new \BadMethodCallException(sprintf(
                    "Custom %s value %s needs a validation callback",
                    __CLASS__,
                    $name
                ));
            }
            self::$validationMap[$name] = $validateCb;
        }

        /** @var callable $validate */
        $validate = $validateCb ?: [$this, self::$validationMap[$name]];
        $value = $validate($value);

        is_null($value) or $this->configs[$name] = $value;

        return $this;
    }

    /**
     * @param  array $configs
     * @return array
     * @see \WCM\WPStarter\Setup\Config::validateGitignore()
     * @see \WCM\WPStarter\Setup\Config::validateBoolOrAskOrUrl()
     * @see \WCM\WPStarter\Setup\Config::validateBoolOrAsk()
     * @see \WCM\WPStarter\Setup\Config::validatePath()
     * @see \WCM\WPStarter\Setup\Config::validatePathArray()
     * @see \WCM\WPStarter\Setup\Config::validateContentDevOperation()
     * @see \WCM\WPStarter\Setup\Config::validateOverwrite()
     * @see \WCM\WPStarter\Setup\Config::validateVerbosity()
     * @see \WCM\WPStarter\Setup\Config::validateSteps()
     * @see \WCM\WPStarter\Setup\Config::validateScripts()
     */
    private function validate(array $configs)
    {
        $wpVersion = empty($configs['wp-version']) ? '0.0.0' : $configs['wp-version'];
        $valid = ['wp-version' => $wpVersion];
        $parsed = self::$defaults;

        array_walk($configs, function ($value, $key) use (&$parsed) {
            $validated = $value;
            if (array_key_exists($key, self::$validationMap)) {
                /** @var callable $validate */
                $validate = [$this, self::$validationMap[$key]];
                $validated = $validate($value);
            }

            is_null($validated) or $parsed[$key] = $validated;
        });

        $parsed['register-theme-folder'] and $parsed['move-content'] = false;

        return array_merge($parsed, $valid);
    }

    /**
     * @param $value
     * @return string|bool|array|null
     */
    private function validateGitignore($value)
    {
        if (is_array($value)) {
            $custom = isset($value['custom']) && is_array($value['custom'])
                ? array_filter($value['custom'], 'is_string')
                : [];
            $default = [
                'wp'         => true,
                'wp-content' => true,
                'vendor'     => true,
                'common'     => true,
            ];
            foreach ($value as $k => $v) {
                if (array_key_exists($k, $default) && $this->validateBool($v) === false) {
                    $default[$k] = false;
                }
            }

            return array_merge(['custom' => $custom], $default);
        }

        return $this->validateBoolOrAskOrUrl($value);
    }

    /**
     * @param $value
     * @return bool|string
     */
    private function validateOverwrite($value)
    {
        if (is_array($value)) {
            return $this->validatePathArray($value);
        }
        if (trim(strtolower((string)$value)) === 'hard') {
            return 'hard';
        }

        return $this->validateBoolOrAsk($value);
    }

    /**
     * @param $value
     * @return int|null
     */
    private function validateVerbosity($value)
    {
        $int = $this->validateInt($value);

        return $int >= 0 && $int < 3 ? $int : null;
    }

    /**
     * @param $value
     * @return string|null
     */
    private function validatePath($value)
    {
        $path = is_string($value)
            ? filter_var(str_replace('\\', '/', $value), FILTER_SANITIZE_URL)
            : null;

        return $path ?: null;
    }

    /**
     * @param $value
     * @return array
     */
    private function validatePathArray($value)
    {
        if (is_array($value)) {
            return array_unique(array_filter(array_map([$this, 'validatePath'], $value)));
        }

        return [];
    }

    /**
     * @param $value
     * @return string|bool|null
     */
    private function validateBoolOrAskOrUrl($value)
    {
        $booleans = [true, false, 1, 0, "true", "false", "1", "0", "yes", "no", "on", "off"];
        if (in_array($value, $booleans, true)) {
            return $this->validateBool($value);
        }

        $ask = $this->validateBoolOrAsk($value);
        if ($ask === 'ask') {
            return $ask;
        }


        return $this->validateUrl($value);
    }

    /**
     * @param $value
     * @return bool|string
     */
    private function validateBoolOrAsk($value)
    {
        $asks = ['ask', 'prompt', 'query', 'interrogate', 'demand'];
        if (is_string($value) && in_array(trim(strtolower($value)), $asks, true)) {
            return 'ask';
        }

        return $this->validateBool($value);
    }

    /**
     * @param $value
     * @return string|null
     */
    private function validateUrl($value)
    {
        if (!is_string($value)) {
            return null;
        }

        return filter_var($value, FILTER_SANITIZE_URL) ?: null;
    }

    /**
     * @param $value
     * @return array|null
     */
    private function validateSteps($value)
    {
        if (!is_array($value)) {
            return null;
        }

        $interface = StepInterface::class;

        $steps = [];
        foreach ($value as $name => $step) {
            is_string($step) and $step = trim($step);
            if (is_string($step) && class_exists($step)) {
                is_subclass_of($step, $interface, true) and $steps[trim($name)] = $step;
            }
        }

        return $steps ?: null;
    }

    /**
     * @param $value
     * @return array
     */
    private function validateScripts($value)
    {
        if (!is_array($value)) {
            return null;
        }

        $allScripts = [];
        foreach ($value as $name => $scripts) {
            is_string($name) or $name = '';
            if (strpos($name, 'pre-') !== 0 && strpos($name, 'post-') !== 0) {
                continue;
            }
            if (is_callable($scripts)) {
                $allScripts[$name] = [$scripts];
                continue;
            }
            if (is_array($scripts)) {
                $scripts = array_filter($scripts, 'is_callable');
                $scripts and $allScripts[$name] = $scripts;
            }
        }

        return $allScripts ?: null;
    }

    /**
     * @param $value
     * @return bool|null|string
     */
    private function validateContentDevOperation($value)
    {
        is_string($value) and $value = trim(strtolower($value));

        if (in_array($value, ['symlink', 'copy'], true)) {
            return $value;
        }

        $ask = $this->validateBoolOrAsk($value);
        if ($ask === 'ask') {
            return $ask;
        }

        $bool = $this->validateBool($value);
        ($bool === true || is_null($bool)) and $bool = null; // when true we return null to force default

        return $bool;
    }

    /**
     * @param $value
     * @return bool
     */
    private function validateBool($value)
    {
        $booleans = [true, false, 1, 0, "true", "false", "1", "0", "yes", "no", "on", "off"];
        is_string($value) and $value = strtolower($value);
        if (in_array($value, $booleans, true)) {
            return (bool)filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return null;
    }

    /**
     * @param $value
     * @return int|null
     */
    private function validateInt($value)
    {
        return is_numeric($value) ? intval($value) : null;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->configs);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->configs[$offset];
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException("Configs can't be set on the fly.");
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException("Configs can't be unset on the fly.");
    }
}
