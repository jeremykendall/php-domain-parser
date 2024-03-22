<?php

declare(strict_types=1);

namespace Pdp;

use Iterator;
use Stringable;
use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

final class Domain implements DomainName
{
    /**
     * @throws SyntaxError
     */
    private function __construct(private readonly RegisteredName $registeredName)
    {
        if (false !== filter_var($this->registeredName->value(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw SyntaxError::dueToUnsupportedType($this->registeredName->toString());
        }
    }

    /**
     * @param array{registeredName: RegisteredName} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['registeredName']);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIDNA2003(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        return new self(RegisteredName::fromIDNA2003($domain));
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIDNA2008(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        return new self(RegisteredName::fromIDNA2008($domain));
    }

    /**
     * @return Iterator<string>
     */
    public function getIterator(): Iterator
    {
        yield from $this->registeredName;
    }

    public function isAscii(): bool
    {
        return $this->registeredName->isAscii();
    }

    public function jsonSerialize(): ?string
    {
        return $this->registeredName->jsonSerialize();
    }

    public function count(): int
    {
        return count($this->registeredName);
    }

    public function value(): ?string
    {
        return $this->registeredName->value();
    }

    public function toString(): string
    {
        return $this->registeredName->toString();
    }

    public function label(int $key): ?string
    {
        return $this->registeredName->label($key);
    }

    /**
     * @return list<int>
     */
    public function keys(?string $label = null): array
    {
        return $this->registeredName->keys($label);
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return $this->registeredName->labels();
    }

    private function newInstance(RegisteredName $registeredName): self
    {
        if ($registeredName->value() === $this->registeredName->value()) {
            return $this;
        }

        return new self($registeredName);
    }

    public function toAscii(): self
    {
        return $this->newInstance($this->registeredName->toAscii());
    }

    public function toUnicode(): self
    {
        return $this->newInstance($this->registeredName->toUnicode());
    }

    /**
     * @throws CannotProcessHost
     */
    public function prepend(DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        return $this->newInstance($this->registeredName->prepend($label));
    }

    /**
     * @throws CannotProcessHost
     */
    public function append(DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        return $this->newInstance($this->registeredName->append($label));
    }

    public function withLabel(int $key, DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        return $this->newInstance($this->registeredName->withLabel($key, $label));
    }

    public function withoutLabel(int ...$keys): self
    {
        return $this->newInstance($this->registeredName->withoutLabel(...$keys));
    }

    /**
     * @throws CannotProcessHost
     */
    public function clear(): self
    {
        return $this->newInstance($this->registeredName->clear());
    }

    /**
     * @throws CannotProcessHost
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return $this->newInstance($this->registeredName->slice($offset, $length));
    }
}
