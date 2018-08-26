<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use Countable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use IteratorAggregate;
use Pdp\Exception\CouldNotLoadTLDs;
use const DATE_ATOM;
use function count;
use function fclose;
use function fopen;
use function sprintf;
use function stream_get_contents;

final class TopLevelDomains implements Countable, IteratorAggregate
{
    /**
     * @var DateTimeImmutable
     */
    private $update;

    /**
     * @var string
     */
    private $version;

    /**
     * @var array
     */
    private $records;

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws Exception If the list can not be loaded from the path
     */
    public static function createFromPath(string $path, $context = null): self
    {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        if (!($resource = @fopen(...$args))) {
            throw new CouldNotLoadTLDs(sprintf('`%s`: failed to open stream: No such file or directory', $path));
        }

        $content = stream_get_contents($resource);
        fclose($resource);

        return self::createFromString($content);
    }

    /**
     * Returns a new instance from a string.
     */
    public static function createFromString(string $content): self
    {
        static $converter;

        $converter = $converter ?? new TLDConverter();

        $data = $converter->convert($content);

        return new self(
            $data['records'],
            $data['version'],
            DateTimeImmutable::createFromFormat(DATE_ATOM, $data['update'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['records'], $properties['version'], $properties['update']);
    }

    /**
     * New instance.
     */
    public function __construct(array $records, string $version, DateTimeInterface $update)
    {
        $this->records = $records;
        $this->version = $version;
        $this->update = $update instanceof DateTime ? DateTimeImmutable::createFromMutable($update) : $update;
    }

    /**
     * Returns the Version ID.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns the List Last Update Info.
     */
    public function getLastUpdate(): DateTimeImmutable
    {
        return $this->update;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->records);
    }

    /**
     * Tells whether the list is empty.
     */
    public function isEmpty(): bool
    {
        return [] === $this->records;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        foreach ($this->records as $tld) {
            yield (new PublicSuffix($tld, PublicSuffix::ICANN_DOMAINS))->toAscii();
        }
    }

    /**
     * Returns an array representation of the list.
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'records' => $this->records,
            'update' => $this->update->format(DATE_ATOM),
        ];
    }

    /**
     * Tells whether the submitted TLD is a valid Top Level Domain.
     */
    public function contains($tld): bool
    {
        try {
            $tld = $tld instanceof Domain ? $tld : new Domain($tld);
            if (1 !== count($tld)) {
                return false;
            }
            $label = $tld->toAscii()->getLabel(0);
            foreach ($this as $tld) {
                if ($tld->getContent() === $label) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns a domain where its public suffix is the found TLD.
     */
    public function resolve($domain): Domain
    {
        try {
            $domain = $domain instanceof Domain ? $domain : new Domain($domain);
            if (!$domain->isResolvable()) {
                return $domain;
            }

            $label = $domain->toAscii()->getLabel(0);
            foreach ($this as $tld) {
                if ($tld->getContent() === $label) {
                    return $domain->resolve($tld);
                }
            }

            return $domain->withPublicSuffix(new PublicSuffix());
        } catch (Exception $e) {
            return new Domain();
        }
    }
}
