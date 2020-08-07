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
 * A PHP keyword tab completion Matcher.
 *
 * This matcher provides completion for all function-like PHP keywords.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class KeywordsMatcher extends AbstractMatcher
{
    protected $keywords = [
        'array', 'clone', 'declare', 'die', 'echo', 'empty', 'eval', 'exit', 'include',
        'include_once', 'isset', 'list', 'print',  'require', 'require_once', 'unset',
    ];

    protected $mandatoryStartKeywords = [
        'die', 'echo', 'print', 'unset',
    ];

    /**
     * Get all (completable) PHP keywords.
     *
     * @return array
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Check whether $keyword is a (completable) PHP keyword.
     *
     * @param string $keyword
     *
     * @return bool
     */
    public function isKeyword($keyword)
    {
        return \in_array($keyword, $this->keywords);
    }

    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = [])
    {
        $input = $this->getInput($tokens);
        if ($input === false) {
            return [];
        }

        return \array_filter($this->keywords, function ($keyword) use ($input) {
            return AbstractMatcher::startsWith($input, $keyword);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token     = \array_pop($tokens);
        $prevToken = \array_pop($tokens);
        $prevTokenBlacklist = [
            self::T_NEW,
            self::T_NS_SEPARATOR,
            self::T_OBJECT_OPERATOR,
            self::T_DOUBLE_COLON,
        ];

        switch (true) {
            // Previous token (blacklist).
            case self::hasToken($prevTokenBlacklist, $prevToken):
                return false;
            // Previous token.
            case self::tokenIsExpressionDelimiter($prevToken):
                return self::tokenIsValidIdentifier($token, true);
            // Current token (whitelist).
            case self::tokenIsValidIdentifier($token, true):
                return true;
        }

        return false;
    }
}
