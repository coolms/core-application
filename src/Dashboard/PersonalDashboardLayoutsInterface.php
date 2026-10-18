<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

/**
 * Where each person's own dashboard arrangements are kept -- the host's to answer.
 *
 * ## Why a port, and not the config store
 *
 * An arrangement is RUNTIME state: one person dragged a card. Written into
 * `config/modules` it became a tracked file in the application's repository --
 * a diff in the served tree that nobody committed -- and, being shared, it
 * rearranged every other admin's dashboard too. The module's YAML stays what
 * it is meant to be: the DEFAULT a module declares, read and never written by
 * the application. What a person changes lives where the host keeps that
 * person's settings, with a version, so two devices never overwrite each other
 * unseen.
 *
 * The platform stores nothing of its own here (a core package writes
 * bookkeeping only): {@see NoPersonalDashboardLayouts} is the answer when the
 * host keeps no per-person settings, and then every dashboard is the module
 * default and nobody can arrange one.
 *
 * ## One version for ALL of a person's dashboards
 *
 * The main dashboard and each section are separate arrangements, but they sit
 * in one store under one version. Arranging the Content section on a phone
 * therefore makes a save of the main dashboard on the desktop stale: safe, and
 * a client says "your dashboards were changed elsewhere", not "this one".
 *
 * ## Entries are UNTRUSTED
 *
 * What comes back from {@see entries()} is whatever was stored. The provider
 * vets each entry the same way it vets a module's YAML -- a bad one costs one
 * card, never the page.
 */
interface PersonalDashboardLayoutsInterface
{
    /**
     * What a dashboard id may look like: `main`, or a widget `group`.
     *
     * The id becomes a key in the person's stored settings, so it is held to a
     * plain token rather than trusted as whatever the query string carried.
     */
    public const string DASHBOARD_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/';

    /** Whether this installation keeps an arrangement per person at all. */
    public function canArrange(): bool;

    /**
     * The version of the signed-in person's arrangements -- all of them, one value.
     *
     * !! Read it BEFORE {@see entries()}. A save landing between the two reads
     * then leaves the version OLDER than the entries, so the next save is
     * refused and re-read; the other order would hand out a version newer than
     * what was shown, and the next save would silently overwrite.
     */
    public function version(): string;

    /**
     * The person's own entries for one dashboard, as stored, or null when they
     * never arranged it (the module default applies).
     *
     * @return list<mixed>|null
     */
    public function entries(string $dashboard): ?array;

    /**
     * Store the person's arrangement of one dashboard; the others are untouched.
     *
     * @param list<array<string, mixed>> $entries         each `widget`, and `columns` / `hidden` only when chosen
     * @param string|null                $expectedVersion the version the arrangement was made from; null = a save
     *                                                    that names none (applied, and counted by the host)
     *
     * @throws StaleDashboardLayoutsException         when $expectedVersion is not the current version
     * @throws DashboardArrangingUnavailableException when {@see canArrange()} is false
     *
     * @return string the version written
     */
    public function save(string $dashboard, array $entries, ?string $expectedVersion): string;

    /**
     * Drop the person's arrangement of one dashboard, so the module default applies again.
     *
     * @throws StaleDashboardLayoutsException         when $expectedVersion is not the current version
     * @throws DashboardArrangingUnavailableException when {@see canArrange()} is false
     *
     * @return string the version written
     */
    public function clear(string $dashboard, ?string $expectedVersion): string;
}
