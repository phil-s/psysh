<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

/**
 * Abstract tab completion Matcher.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
abstract class AbstractMatcher
{
    /** Syntax types */
    const CONSTANT_SYNTAX = '^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$';
    const VAR_SYNTAX = '^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$';
    const MISC_OPERATORS = '+-*/^|&';
    /** Token values */
    const T_OPEN_TAG = 'T_OPEN_TAG';
    const T_VARIABLE = 'T_VARIABLE';
    const T_OBJECT_OPERATOR = 'T_OBJECT_OPERATOR';
    const T_DOUBLE_COLON = 'T_DOUBLE_COLON';
    const T_NEW = 'T_NEW';
    const T_CLONE = 'T_CLONE';
    const T_NS_SEPARATOR = 'T_NS_SEPARATOR';
    const T_STRING = 'T_STRING';
    const T_WHITESPACE = 'T_WHITESPACE';
    const T_AND_EQUAL = 'T_AND_EQUAL';
    const T_BOOLEAN_AND = 'T_BOOLEAN_AND';
    const T_BOOLEAN_OR = 'T_BOOLEAN_OR';

    const T_ENCAPSED_AND_WHITESPACE = 'T_ENCAPSED_AND_WHITESPACE';
    const T_REQUIRE = 'T_REQUIRE';
    const T_REQUIRE_ONCE = 'T_REQUIRE_ONCE';
    const T_INCLUDE = 'T_INCLUDE';
    const T_INCLUDE_ONCE = 'T_INCLUDE_ONCE';

    /**
     * Check whether this matcher can provide completions for $tokens.
     *
     * The final token is the 'word' to be completed.  If the input
     * did not end in a valid identifier prefix then the final token
     * will be an empty string.
     *
     * All whitespace tokens have been removed from the $tokens array.
     *
     * @see AutoCompleter::processCallback().
     *
     * @param array $tokens Tokenized readline input
     *
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
        return false;
    }

    /**
     * Get the input word to be completed, based on the tokenised input.
     *
     * Note that this may not be identical to the word which readline needs to
     * complete (see AutoCompleter::WORD_BREAK_CHARS), and so Matchers must
     * take care to return candidate values that match what readline wants.
     *
     * We return the string value of the final token if it is valid, and false
     * if that token is invalid.  By default, the token is valid if it is
     * valid prefix (including '') for a PHP identifier.
     *
     * @param array      $tokens      Tokenized readline input (see token_get_all)
     * @param array|null $validTokens Acceptable tokens
     *
     * @return string|bool
     */
    protected function getInput(array $tokens, array $validTokens = null)
    {
        $token = \array_pop($tokens);
        $input = \is_array($token) ? $token[1] : $token;

        if (isset($validTokens)) {
            if (self::hasToken($validTokens, $token)) {
                return $input;
            }
        } elseif (self::tokenIsValidIdentifier($token, true)) {
            return $input;
        }

        return false;
    }

    /**
     * Get current namespace and class (if any) from readline input.
     *
     * @param array $tokens Tokenized readline input (see token_get_all)
     *
     * @return string
     */
    protected function getNamespaceAndClass($tokens)
    {
        $validTokens = [
            self::T_NS_SEPARATOR,
            self::T_STRING,
        ];

        $token = \array_pop($tokens);
        if (!self::hasToken($validTokens, $token)
            && !self::tokenIsValidIdentifier($token, true)
        ) {
            return '';
        }
        $class = \is_array($token) ? $token[1] : $token;

        while (self::hasToken($validTokens, $token = \array_pop($tokens))) {
            if (self::needCompleteClass($token)) {
                break;
            }

            $class = $token[1].$class;
        }

        return $class;
    }

    /**
     * Provide tab completion matches for readline input.
     *
     * @param array $tokens information substracted with get_token_all
     * @param array $info   readline_info object
     *
     * @return array The matches resulting from the query
     */
    abstract public function getMatches(array $tokens, array $info = []);

    /**
     * Check whether $word starts with $prefix.
     *
     * @param string $prefix
     * @param string $word
     *
     * @return bool
     */
    public static function startsWith($prefix, $word)
    {
        return \preg_match(\sprintf('#^%s#', \preg_quote($prefix)), $word);
    }

    /**
     * Check whether $token matches a given syntax pattern.
     *
     * @param mixed  $token  A PHP token (see token_get_all)
     * @param string $syntax A syntax pattern (default: variable pattern)
     *
     * @return bool
     */
    public static function hasSyntax($token, $syntax = self::VAR_SYNTAX)
    {
        if (!\is_array($token)) {
            return false;
        }

        $regexp = \sprintf('#%s#', $syntax);

        return (bool) \preg_match($regexp, $token[1]);
    }

    /**
     * Check whether $token is of type $which.
     *
     * $which may either be a token type name (e.g. self::T_VARIABLE),
     * or a literal string token (e.g. '+').
     *
     * @param mixed  $token A PHP token (see token_get_all)
     * @param string $which A PHP token type
     *
     * @return bool
     */
    public static function tokenIs($token, $which)
    {
        if (\is_array($token)) {
            $token = \token_name($token[0]);
        }

        return $token === $which;
    }

    /**
     * Check whether $token is an operator.
     *
     * @param mixed $token A PHP token (see token_get_all)
     *
     * @return bool
     */
    public static function isOperator($token)
    {
        if (!\is_string($token) || $token === '') {
            return false;
        }

        return \strpos(self::MISC_OPERATORS, $token) !== false;
    }

    /**
     * Check whether $token is a valid prefix for a PHP identifier.
     *
     * @param mixed $token      A PHP token (see token_get_all)
     * @param bool  $allowEmpty Whether an empty string is valid
     *
     * @return bool
     */
    public static function tokenIsValidIdentifier($token, $allowEmpty = false)
    {
        // See AutoCompleter::processCallback() regarding the '' token.
        if ($token === '') {
            return $allowEmpty;
        }

        return self::hasSyntax($token, self::CONSTANT_SYNTAX);
    }

    /**
     * Check whether $token 'separates' PHP expressions, meaning that
     * whatever follows is a new expression.
     *
     * Separators include the initial T_OPEN_TAG token, and ";".
     *
     * @param mixed $token A PHP token (see token_get_all)
     *
     * @return bool
     */
    public static function tokenIsExpressionDelimiter($token)
    {
        return $token === ';' || self::tokenIs($token, self::T_OPEN_TAG);
    }

    /**
     * Used both to test $tokens[1] (i.e. following T_OPEN_TAG) to
     * see whether it's a PsySH introspection command, and also by
     * self::getNamespaceAndClass() to prevent these commands from
     * being considered part of the namespace (which could happen
     * on account of all the whitespace tokens having been removed
     * from the tokens array by AutoCompleter::processCallback().
     */
    public static function needCompleteClass($token)
    {
        return \in_array($token[1], ['doc', 'ls', 'show', 'completions']);
    }

    /**
     * Check whether $token has a type which is present in $coll.
     *
     * $coll may include a mixture of token type names (e.g. self::T_VARIABLE),
     * and literal string tokens (e.g. '+').
     *
     * @param array $coll  A list of token types
     * @param mixed $token A PHP token (see token_get_all)
     *
     * @return bool
     */
    public static function hasToken(array $coll, $token)
    {
        if (\is_array($token)) {
            $token = \token_name($token[0]);
        }

        return \in_array($token, $coll, true);
    }
}
