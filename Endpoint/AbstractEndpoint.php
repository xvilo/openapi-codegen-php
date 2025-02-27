<?php

declare(strict_types=1);

/**
 * This file is part of the Elastic OpenAPI PHP code generator.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Elastic\OpenApi\Codegen\Endpoint;

use ADS\Util\ArrayUtil;
use ADS\ValueObjects\ValueObject;
use UnexpectedValueException;

/**
 * Abstract endpoint implementation.
 */
abstract class AbstractEndpoint implements EndpointInterface
{
    protected string $method;
    protected string $uri;
    /** @var array<string>  */
    protected array $routeParams = [];
    /** @var array<string>  */
    protected array $paramWhitelist = [];
    /** @var array<string, mixed>  */
    protected array $params = [];
    /** @var array<string>|null  */
    protected ?array $body = null;
    /** @var array<string>|null  */
    protected ?array $formData = null;
    protected bool $snakeCasedParams = false;
    protected bool $snakeCasedBody = false;
    protected bool $snakeCasedFormData = false;

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        $uri = $this->uri;

        foreach ($this->routeParams as $paramName) {
            $uri = str_replace(sprintf('{%s}', $paramName), $this->params[$paramName], $uri);
        }

        return ltrim($uri, '/');
    }

    /**
     * @return array<string>
     */
    private function paramWhitelist(): array
    {
        return array_map(
            static fn (string $param) => substr($param, -2) === '[]' ? rtrim($param, '[]') : $param,
            $this->paramWhitelist
        );
    }

    /**
     * {@inheritdoc}
     */
    public function params(): array
    {
        $paramWhiteList = $this->paramWhitelist();

        return ArrayUtil::noNullItems(
            array_filter(
                $this->params,
                static fn (string $paramName) => in_array($paramName, $paramWhiteList),
                ARRAY_FILTER_USE_KEY
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function body(): ?array
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function setBody(?array $body)
    {
        $this->body = $this->transformData($body);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function formData(): ?array
    {
        return $this->formData;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormData(?array $formData)
    {
        $this->formData = $this->transformData($formData);

        return $this;
    }

    /**
     * @param array<mixed>|null $data
     *
     * @return mixed[]|null
     */
    private function transformData(?array $data): ?array
    {
        if ($data === null) {
            return $data;
        }

        $data = ArrayUtil::removePrefixFromKeys(
            ArrayUtil::noNullItems($data),
            'prefixNumber'
        );

        if ($this->snakeCasedBody) {
            /** @var array<mixed> $data */
            $data = ArrayUtil::toSnakeCasedKeys($data);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setParams(?array $params)
    {
        if ($params === null) {
            return $this;
        }

        if ($this->snakeCasedParams) {
            /** @var array<string, mixed> $params */
            $params = ArrayUtil::toSnakeCasedKeys($params);
        }

        $this->checkParams($params);

        $params = array_map(
            static fn ($paramValue) => $paramValue instanceof ValueObject ? $paramValue->toValue() : $paramValue,
            $params
        );

        $this->params = $params;

        return $this;
    }

    /**
     * Loop over the param to check all params are into the whitelist.
     *
     * @param array<string, mixed>|null $params
     *
     * @throws UnexpectedValueException
     */
    private function checkParams(?array $params): void
    {
        if ($params === null) {
            return;
        }

        $whitelist = array_merge($this->paramWhitelist(), $this->routeParams);
        $invalidParams = array_diff(array_keys($params), $whitelist);
        $countInvalid = count($invalidParams);

        if ($countInvalid <= 0) {
            return;
        }

        $whitelist = implode('", "', $whitelist);
        $invalidParams = implode('", "', $invalidParams);
        $message = '"%s" is not a valid parameter. Allowed parameters are "%s".';
        if ($countInvalid > 1) {
            $message = '"%s" are not valid parameters. Allowed parameters are "%s".';
        }

        throw new UnexpectedValueException(
            sprintf($message, $invalidParams, $whitelist)
        );
    }

    /**
     * @return static
     */
    public function setSnakeCasedParams(bool $snakeCasedParams)
    {
        $this->snakeCasedParams = $snakeCasedParams;

        return $this;
    }

    /**
     * @return static
     */
    public function setSnakeCasedBody(bool $snakeCasedBody)
    {
        $this->snakeCasedBody = $snakeCasedBody;

        return $this;
    }

    /**
     * @return static
     */
    public function setSnakeCasedFormData(bool $snakeCasedFormData)
    {
        $this->snakeCasedFormData = $snakeCasedFormData;

        return $this;
    }
}
