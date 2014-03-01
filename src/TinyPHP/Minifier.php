<?php

namespace TinyPHP;

class Minifier
{
    private static $EXCLUDES = array(
        '$GLOBALS',
        '$_SERVER',
        '$_GET',
        '$_POST',
        '$_FILES',
        '$_REQUEST',
        '$_SESSION',
        '$_ENV',
        '$_COOKIE',
        '$php_errormsg',
        '$HTTP_RAW_POST_DATA',
        '$http_response_header',
        '$argc',
        '$argv',
        '$this'
    );

    private static function isLabel($str)
    {
        return preg_match('~[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+~', $str);
    }

    public static function getTiny($src, $replaceVariables = false, $whitespaces = false, $comments = false,
                                   $excludes = array()
    )
    {
        //create excluded variables array
        if (!empty($excludes)) {
            $excludes = array_merge(self::$EXCLUDES, $excludes);
            $excludes = array_unique($excludes);
        } else {
            $excludes = self::$EXCLUDES;
        }

        //generate tokens from snippet
        $tokens = token_get_all($src);

        //get all variables in snippet, remove comments
        $variables = array();
        $result = '';
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                $result .= $token;
                continue;
            }
            if ($token[0] == T_VARIABLE && !in_array($token[1], $excludes)) {
                $variables[] = $token[1];
            }
            if (!$comments) {
                if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                    continue;
                }
            }
            $result .= $token[1];
        }
        $variables = array_unique($variables);

        // generate replace values for all variables
        $variableReplacement = array();
        if (!empty($variables)) {
            $count = 0;
            while ($variable = current($variables)) {
                $mod = $count % 26;

                $repeatChars = ((int)floor($count / 26)) + 1;

                $replaceInt = 97 + $mod;
                $value = chr(36);

                for ($i = 1; $i <= $repeatChars; $i++) {
                    $value .= chr($replaceInt);
                }

                if (!in_array($value, $variables) && !in_array($value, $excludes)) {
                    $variableReplacement[$variable] = $value;
                    next($variables);
                }
                $count++;
            }
        }

        //get all tokens from previous iteration
        $tokens = token_get_all($result);

        //replace all variables, remove whitespace
        $result = '';
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                $result .= $token;
                continue;
            }
            if ($replaceVariables) {
                if ($token[0] == T_VARIABLE && !in_array($token[1], $excludes)) {
                    $key = $token[1];
                    $result .= $variableReplacement[$key];
                    continue;
                }
            }
            if ($token[0] == T_WHITESPACE && !$whitespaces) {
                if (isset($tokens[$i - 1]) && isset($tokens[$i + 1]) && is_array($tokens[$i - 1])
                    && is_array($tokens[$i + 1])
                    && self::isLabel($tokens[$i - 1][1])
                    && self::isLabel($tokens[$i + 1][1])
                ) {
                    $result .= ' ';
                }
            } else if ($token[0] == T_CASE) {
                $result .= $token[1] . ' ';
            } else {
                $result .= $token[1];
            }
        }

        return $result;
    }
}