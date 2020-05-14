<?php
/**
 * @name VariableToJson.php
 * @link https://alexkratky.com                         Author website
 * @link https://panx.eu/docs/                          Documentation
 * @link https://github.com/AlexKratky/VariableToJson/  Github Repository
 * @author Alex Kratky <alex@panx.dev>
 * @copyright Copyright (c) 2020 Alex Kratky
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @description Convert any variable or object to json. Part of panx-framework.
 */

declare(strict_types=1);

namespace AlexKratky;

class VariableToJson {
    private static $hashes = [];

    private static $maxDepth = 5;
    private static $maxLength = 300;

    public function setDepth($maxDepth = 5)
    {
        self::$maxDepth = $maxDepth;
    }

    public function setLength($maxLength = 300) {
        self::$maxLength = $maxLength;
    }
    
    public static function convert($var, $pretty = false, $raw = false) {
        self::$hashes = [];
        $var = self::convert_raw($var);
        $var['location'] = self::findLocation(is_callable($var));
        return ($raw ? $var : ($pretty ? json_encode(($var), JSON_HEX_QUOT | JSON_PRETTY_PRINT) : json_encode(($var), JSON_HEX_QUOT)));
    }

    public static function convert_raw($var, $name = null, $modifier = null, $depth = 0) {
        if(!is_object($var) && !is_callable($var)) {
            if(is_string($var)) {
                return ([
                    'name' => $name,
                    'type' => gettype($var),
                    'modifier' => $modifier,
                    'value' => substr($var, 0, self::$maxLength)
                ]);
            }
            return (
                [
                    'name' => $name,
                    'type' => gettype($var),
                    'modifier' => $modifier,
                    'value' => $var
                ]
            );
        } elseif(is_callable($var)) {
            return (
                [
                    'name' => $name,
                    'type' => 'function',
                    'modifier' => $modifier,
                    'function' => self::exportFunction($var)
                ]
            );
        } elseif(is_iterable($var)) {
            return (
                [
                    'name' => $name,
                    'type' => 'iterable',
                    'modifier' => $modifier,
                ]
            );
        } elseif(is_resource($var)) {
            return (
                [
                    'name' => $name,
                    'type' => 'resource',
                    'resource_type' => get_resource_type($var)
                ]
            );
        } elseif(is_object($var)) {
            return self::object_to_json($var, (++$depth));
        }
    }

    public static function object_to_json($object, $depth = 0) {
        $reflector = new \ReflectionClass($object);

        if(in_array(spl_object_hash($object), self::$hashes)) {
            $var = [
            'type' => 'Object',
                'name' =>  $reflector->getName(),
                'namespace' => $reflector->getNamespaceName(),
                'full_hash' => spl_object_hash($object),
                'hash' => str_replace("0", "", spl_object_hash($object)),
                'filename' => $reflector->getFileName(),
                'constants' => '#RECURSION',
                'variables' => '#RECURSION',
                'methods' => '#RECURSION',
                'parent' => '#RECURSION',
                'implements' => '#RECURSION'
            ];
            return $var;
        }

        if($depth > self::$maxDepth) {
            $var = [
                'type' => 'Object',
                'name' =>  $reflector->getName(),
                'namespace' => $reflector->getNamespaceName(),
                'full_hash' => spl_object_hash($object),
                'hash' => str_replace("0", "", spl_object_hash($object)),
                'filename' => $reflector->getFileName(),
                'constants' => '#MAX_DEPTH',
                'variables' => '#MAX_DEPTH',
                'methods' => '#MAX_DEPTH',
                'parent' => '#MAX_DEPTH',
                'implements' => '#MAX_DEPTH'
            ];
            return $var;
        }

        self::$hashes[] = spl_object_hash($object);

        $var = [
            'type' => 'Object',
            'name' =>  $reflector->getName(),
            'namespace' => $reflector->getNamespaceName(),
            'full_hash' => spl_object_hash($object),
            'hash' => str_replace("0", "", spl_object_hash($object)),
            'filename' => $reflector->getFileName(),
            'constants' => [],
            'variables' => [],
            'methods' => [],
            'parent' => ($reflector->getParentClass() === false ? null : $reflector->getParentClass()),
            'implements' => $reflector->getInterfaceNames()
        ];
        $constants = $reflector->getReflectionConstants();
        $properties = $reflector->getProperties();
        $methods = $reflector->getMethods();

        foreach ($constants as $const) {
            $var['constants'][] = self::convert_raw($const->getValue(), $const->getName(), implode(" ", \Reflection::getModifierNames($const->getModifiers())));
        }

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $var['variables'][] = self::convert_raw($property->getValue($object), $property->getName(), implode(" ", \Reflection::getModifierNames($property->getModifiers())), $depth);
        }

        foreach ($methods as $method) {
            $params = $method->getParameters();
            $param_arr = [];

            foreach ($params as $param) {
                $param_arr[] = [
                    'name' => $param->getName(),
                    'default_value' => ($param->isOptional() && $param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null,
                    'default_value_const' => ($param->isOptional() && $param->isDefaultValueConstant()) ? $param->getDefaultValueConstantName() : null,
                    'type' => ($param->getType() instanceof \ReflectionNamedType) ? $param->getType()->getName() : $param->getType(),
                    'is_optional' => $param->isOptional()
                ];
            }

            $var['methods'][] = [
                'name' => $method->getName(),
                'modifiers' => implode(' ', \Reflection::getModifierNames($method->getModifiers())),
                'parameters' => $param_arr
            ];
        }

        return $var;
    }

    /**
     * return [
     *  file,
     *  line,
     *  code,
     *  variable / code
     * ]
     */
    public static function findLocation($is_closure = false): ?array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
            if (isset($item['class']) && $item['class'] === __CLASS__) {
                $location = $item;
                continue;
            } elseif (isset($item['function'])) {
                try {
                    $reflection = isset($item['class'])
                        ? new \ReflectionMethod($item['class'], $item['function'])
                        : new \ReflectionFunction($item['function']);
                    if ($reflection->isInternal()) {
                        $location = $item;
                        continue;
                    }
                } catch (\ReflectionException $e) {
                }
            }
            break;
        }

        if($is_closure) {
            // get start line of closure
        }

        if (isset($location['file'], $location['line']) && is_file($location['file'])) {
            $lines = file($location['file']);
            $line = $lines[$location['line'] - 1];
            return [
                $location['file'],
                $location['line'],
                trim(preg_match('/\w*VariableToJson::convert\((.*?)\)/i', $line, $m) ? $m[0] : $line),
                preg_match('/\w*VariableToJson::convert\((.*?)\)\)/i', $line, $m) ? trim(explode(",", $m[1])[0]) : null,
            ];
        }
        return null;
    }

    private static function exportFunction($var) {
        $rc = new \ReflectionFunction($var);
        $res = [];
        foreach ($rc->getParameters() as $param) {
            $res[] = [
                'name' => $param->getName(),
                'default_value' => ($param->isOptional() && $param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null,
                'default_value_const' => ($param->isOptional() && $param->isDefaultValueConstant()) ? $param->getDefaultValueConstantName() : null,
                'type' => ($param->getType() instanceof \ReflectionNamedType) ? $param->getType()->getName() : $param->getType(),
                'is_optional' => $param->isOptional()
            ];
        }
        return [
            'file' => $rc->getFileName(),
            'line' => [$rc->getStartLine(), $rc->getEndLine()],
            'parameters' => $res,
            'return_type' => $rc->getReturnType()
        ]; //getStaticVariables
    }

}
