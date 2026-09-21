<?php

declare(strict_types=1);

final class HalsteadAnalyzer
{
    /** @var array<int, string> */
    private const KEYWORD_OPERATORS = [
        T_IF => 'if',
        T_ELSEIF => 'elseif',
        T_ELSE => 'else',
        T_SWITCH => 'switch',
        T_CASE => 'case',
        T_DEFAULT => 'default',
        T_MATCH => 'match',
        T_FOR => 'for',
        T_FOREACH => 'foreach',
        T_WHILE => 'while',
        T_DO => 'do',
        T_BREAK => 'break',
        T_CONTINUE => 'continue',
        T_RETURN => 'return',
        T_FUNCTION => 'function',
        T_FN => 'fn',
        T_TRY => 'try',
        T_CATCH => 'catch',
        T_FINALLY => 'finally',
        T_THROW => 'throw',
        T_ECHO => 'echo',
        T_PRINT => 'print',
        T_AS => 'as',
        T_NEW => 'new',
        T_CLONE => 'clone',
        T_INCLUDE => 'include',
        T_INCLUDE_ONCE => 'include_once',
        T_REQUIRE => 'require',
        T_REQUIRE_ONCE => 'require_once',
        T_EVAL => 'eval',
        T_EXIT => 'exit',
        T_ISSET => 'isset',
        T_EMPTY => 'empty',
        T_UNSET => 'unset',
        T_LIST => 'list',
        T_ARRAY => 'array',
        T_CALLABLE => 'callable',
        T_YIELD => 'yield',
        T_YIELD_FROM => 'yield from',
        T_INSTANCEOF => 'instanceof',
        T_GLOBAL => 'global',
        T_STATIC => 'static',
        T_ABSTRACT => 'abstract',
        T_FINAL => 'final',
        T_PRIVATE => 'private',
        T_PROTECTED => 'protected',
        T_PUBLIC => 'public',
        T_READONLY => 'readonly',
        T_CLASS => 'class',
        T_INTERFACE => 'interface',
        T_TRAIT => 'trait',
        T_ENUM => 'enum',
        T_EXTENDS => 'extends',
        T_IMPLEMENTS => 'implements',
        T_NAMESPACE => 'namespace',
        T_USE => 'use',
        T_CONST => 'const',
        T_DECLARE => 'declare',
        T_GOTO => 'goto',
    ];

    /** @var array<int, string> */
    private const TOKEN_OPERATORS = [
        T_BOOLEAN_AND => '&&',
        T_BOOLEAN_OR => '||',
        T_LOGICAL_AND => 'and',
        T_LOGICAL_OR => 'or',
        T_LOGICAL_XOR => 'xor',
        T_IS_EQUAL => '==',
        T_IS_NOT_EQUAL => '!=',
        T_IS_IDENTICAL => '===',
        T_IS_NOT_IDENTICAL => '!==',
        T_IS_SMALLER_OR_EQUAL => '<=',
        T_IS_GREATER_OR_EQUAL => '>=',
        T_SPACESHIP => '<=>',
        T_COALESCE => '??',
        T_INC => '++',
        T_DEC => '--',
        T_PLUS_EQUAL => '+=',
        T_MINUS_EQUAL => '-=',
        T_MUL_EQUAL => '*=',
        T_DIV_EQUAL => '/=',
        T_MOD_EQUAL => '%=',
        T_CONCAT_EQUAL => '.=',
        T_AND_EQUAL => '&=',
        T_OR_EQUAL => '|=',
        T_XOR_EQUAL => '^=',
        T_SL_EQUAL => '<<=',
        T_SR_EQUAL => '>>=',
        T_POW => '**',
        T_POW_EQUAL => '**=',
        T_COALESCE_EQUAL => '??=',
        T_DOUBLE_ARROW => '=>',
        T_OBJECT_OPERATOR => '->',
        T_NULLSAFE_OBJECT_OPERATOR => '?->',
        T_PAAMAYIM_NEKUDOTAYIM => '::',
        T_SL => '<<',
        T_SR => '>>',
    ];

    /** @var array<string, true> */
    private const BUILTIN_TYPES = [
        'array' => true,
        'bool' => true,
        'callable' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'iterable' => true,
        'mixed' => true,
        'never' => true,
        'null' => true,
        'object' => true,
        'parent' => true,
        'self' => true,
        'static' => true,
        'string' => true,
        'true' => true,
        'void' => true,
    ];

    /** @var array<int, true> */
    private const IGNORED_TOKENS = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
        T_OPEN_TAG => true,
        T_OPEN_TAG_WITH_ECHO => true,
        T_CLOSE_TAG => true,
    ];

    /** @var array<int, true> */
    private const DECLARATION_TOKENS = [
        T_FUNCTION => true,
        T_FN => true,
        T_CLASS => true,
        T_INTERFACE => true,
        T_TRAIT => true,
        T_ENUM => true,
        T_NAMESPACE => true,
    ];

    /** @var array<int, true> */
    private const PARENTHESIZED_KEYWORDS = [
        T_IF => true,
        T_ELSEIF => true,
        T_SWITCH => true,
        T_MATCH => true,
        T_FOR => true,
        T_FOREACH => true,
        T_WHILE => true,
        T_CATCH => true,
        T_DECLARE => true,
        T_ISSET => true,
        T_EMPTY => true,
        T_UNSET => true,
        T_LIST => true,
        T_ARRAY => true,
        T_FUNCTION => true,
        T_FN => true,
    ];

    private const SYMBOL_OPERATORS = '=+-*/%.<>!&|^~?:,@;';

    /**
     * @return array{
     *     operators: array<string, int>,
     *     operands: array<string, int>,
     *     eta1: int,
     *     eta2: int,
     *     N1: int,
     *     N2: int,
     *     eta: int,
     *     N: int,
     *     V: float
     * }
     */
    public function analyze(string $code): array
    {
        $this->validateSource($code);

        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new InvalidArgumentException(
                'PHP-код содержит синтаксическую ошибку: ' . $error->getMessage(),
                0,
                $error
            );
        }

        $operators = [];
        $operands = [];
        $delimiterStack = [];
        $significant = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                [$id, $text] = $token;

                if (isset(self::IGNORED_TOKENS[$id])) {
                    continue;
                }

                if (isset(self::KEYWORD_OPERATORS[$id])) {
                    $this->increment($operators, self::KEYWORD_OPERATORS[$id]);
                } elseif (isset(self::TOKEN_OPERATORS[$id])) {
                    $this->increment($operators, self::TOKEN_OPERATORS[$id]);
                } elseif ($this->isDirectOperandToken($id)) {
                    $this->increment($operands, $text);
                } elseif ($this->isIdentifierToken($id)) {
                    $this->classifyIdentifier($tokens, $index, $text, $operators, $operands);
                }

                $significant[] = $token;
                continue;
            }

            if ($token === '(' || $token === '[' || $token === '{') {
                $delimiterStack[] = [
                    'symbol' => $token,
                    'attached' => $token === '(' && $this->isAttachedParenthesis($significant),
                ];
                $significant[] = $token;
                continue;
            }

            if ($token === ')' || $token === ']' || $token === '}') {
                $this->closeDelimiter($token, $delimiterStack, $operators);
                $significant[] = $token;
                continue;
            }

            if ($token === '"' || $token === '`' || $token === '\\') {
                $significant[] = $token;
                continue;
            }

            if (str_contains(self::SYMBOL_OPERATORS, $token)) {
                $this->increment($operators, $token);
            }

            $significant[] = $token;
        }

        if ($delimiterStack !== []) {
            throw new InvalidArgumentException('PHP-код содержит незакрытый парный разделитель.');
        }

        return $this->buildResult($operators, $operands);
    }

    private function validateSource(string $code): void
    {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Введите PHP-код для анализа.');
        }

        if (preg_match('/<\?(?:php|=)/i', $code) !== 1) {
            throw new InvalidArgumentException('Код должен содержать открывающий PHP-тег.');
        }
    }

    private function isDirectOperandToken(int $id): bool
    {
        return in_array(
            $id,
            [
                T_VARIABLE,
                T_LNUMBER,
                T_DNUMBER,
                T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE,
                T_STRING_VARNAME,
                T_NUM_STRING,
            ],
            true
        );
    }

    private function isIdentifierToken(int $id): bool
    {
        return in_array(
            $id,
            [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE],
            true
        );
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, int> $operators
     * @param array<string, int> $operands
     */
    private function classifyIdentifier(
        array $tokens,
        int $index,
        string $text,
        array &$operators,
        array &$operands
    ): void {
        $lower = strtolower(ltrim($text, '\\'));

        if (in_array($lower, ['true', 'false', 'null'], true)) {
            $this->increment($operands, $lower);
            return;
        }

        $previous = $this->neighborToken($tokens, $index, -1);
        $next = $this->neighborToken($tokens, $index, 1);
        $previousId = is_array($previous) ? $previous[0] : null;

        if ($previousId !== null && isset(self::DECLARATION_TOKENS[$previousId])) {
            return;
        }

        if ($next === '(') {
            $this->increment($operators, $text . '()');
            return;
        }

        if (isset(self::BUILTIN_TYPES[$lower])) {
            return;
        }

        $this->increment($operands, $text);
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private function neighborToken(array $tokens, int $index, int $direction): array|string|null
    {
        for ($cursor = $index + $direction; isset($tokens[$cursor]); $cursor += $direction) {
            $candidate = $tokens[$cursor];

            if (is_array($candidate) && isset(self::IGNORED_TOKENS[$candidate[0]])) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /** @param list<array{0: int, 1: string, 2: int}|string> $significant */
    private function isAttachedParenthesis(array $significant): bool
    {
        $previous = $significant[array_key_last($significant)] ?? null;

        if (is_array($previous)) {
            $id = $previous[0];

            return $this->isIdentifierToken($id)
                || isset(self::PARENTHESIZED_KEYWORDS[$id]);
        }

        return false;
    }

    /**
     * @param list<array{symbol: string, attached: bool}> $stack
     * @param array<string, int> $operators
     */
    private function closeDelimiter(string $closing, array &$stack, array &$operators): void
    {
        $expectedOpening = [')' => '(', ']' => '[', '}' => '{'][$closing];
        $entry = array_pop($stack);

        if ($entry === null || $entry['symbol'] !== $expectedOpening) {
            throw new InvalidArgumentException('Нарушен порядок парных разделителей PHP-кода.');
        }

        if ($closing === ')' && $entry['attached']) {
            return;
        }

        $this->increment($operators, $expectedOpening . $closing);
    }

    /** @param array<string, int> $frequencies */
    private function increment(array &$frequencies, string $item): void
    {
        $frequencies[$item] = ($frequencies[$item] ?? 0) + 1;
    }

    /**
     * @param array<string, int> $operators
     * @param array<string, int> $operands
     * @return array{
     *     operators: array<string, int>,
     *     operands: array<string, int>,
     *     eta1: int,
     *     eta2: int,
     *     N1: int,
     *     N2: int,
     *     eta: int,
     *     N: int,
     *     V: float
     * }
     */
    private function buildResult(array $operators, array $operands): array
    {
        ksort($operators, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($operands, SORT_NATURAL | SORT_FLAG_CASE);
        $eta1 = count($operators);
        $eta2 = count($operands);
        $N1 = array_sum($operators);
        $N2 = array_sum($operands);
        $eta = $eta1 + $eta2;
        $N = $N1 + $N2;

        return [
            'operators' => $operators,
            'operands' => $operands,
            'eta1' => $eta1,
            'eta2' => $eta2,
            'N1' => $N1,
            'N2' => $N2,
            'eta' => $eta,
            'N' => $N,
            'V' => $eta > 0 ? $N * log($eta, 2) : 0.0,
        ];
    }
}
