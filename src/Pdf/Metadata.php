<?php

declare(strict_types=1);

namespace Stanko\Pdf;

use DateTimeImmutable;
use Stanko\Pdf\Exception\CreatedAtIsNotSetException;

final class Metadata
{
    private function __construct(
        private ?string $title,
        private ?string $author,
        private ?string $subject,
        private ?string $keywords,
        private ?string $creator,
        private ?DateTimeImmutable $createdAt,
    ) {
    }

    public static function empty(): self
    {
        return new self(
            title: null,
            author: null,
            subject: null,
            keywords: null,
            creator: null,
            createdAt: null,
        );
    }

    public function withTitle(string $title): static
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function withAuthor(string $author): static
    {
        $clone = clone $this;
        $clone->author = $author;

        return $clone;
    }

    public function withSubject(string $subject): static
    {
        $clone = clone $this;
        $clone->subject = $subject;

        return $clone;
    }

    public function withKeywords(string $keywords): static
    {
        $clone = clone $this;
        $clone->keywords = $keywords;

        return $clone;
    }

    public function createdBy(string $creator): static
    {
        $clone = clone $this;
        $clone->creator = $creator;

        return $clone;
    }

    public function createdAt(DateTimeImmutable $createdAt): static
    {
        $clone = clone $this;
        $clone->createdAt = $createdAt;

        return $clone;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        if ($this->createdAt === null) {
            throw new CreatedAtIsNotSetException('You forgot to call $pdf->createdAt()');
        }

        $date = $this->createdAt->format('YmdHisO');

        $return = [];

        if ($this->author !== null) {
            $return['Author'] = $this->author;
        }

        if ($this->creator !== null) {
            $return['Creator'] = $this->creator;
        }

        if ($this->keywords !== null) {
            $return['Keywords'] = $this->keywords;
        }

        if ($this->subject !== null) {
            $return['Subject'] = $this->subject;
        }

        if ($this->title !== null) {
            $return['Title'] = $this->title;
        }

        $return['CreationDate'] = 'D:' . substr($date, 0, -2) . "'" . substr($date, -2) . "'";

        return $return;
    }
}
