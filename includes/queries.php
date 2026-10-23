<?php
declare(strict_types=1);

function approved_conference_where(): string
{
    return "c.status = 'approved' AND c.is_deactivated = 0";
}

function fetch_featured_conferences(int $limit = 6): array
{
    $sql = "SELECT c.*, ca.name topic_name, co.name country_name, ci.name city_name,
                   o.organization_name organizer_name, o.slug organizer_slug
            FROM conferences c
            LEFT JOIN categories ca ON ca.id = c.category_id
            LEFT JOIN countries co ON co.id = c.country_id
            LEFT JOIN cities ci ON ci.id = c.city_id
            LEFT JOIN organizers o ON o.id = c.organizer_id
            WHERE " . approved_conference_where() . " AND c.is_featured = 1 AND c.end_date >= CURDATE()
            ORDER BY c.start_date ASC, c.created_at DESC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_active_categories_with_counts(int $limit = 24): array
{
    $sql = "SELECT ca.*, COUNT(c.id) conference_count
            FROM categories ca
            JOIN conferences c ON c.category_id = ca.id AND " . approved_conference_where() . " AND c.end_date >= CURDATE()
            WHERE ca.status = 'active'
            GROUP BY ca.id
            HAVING conference_count > 0
            ORDER BY conference_count DESC, ca.name ASC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_countries_with_counts(int $limit = 24): array
{
    $sql = "SELECT co.*, COUNT(c.id) conference_count
            FROM countries co
            JOIN conferences c ON c.country_id = co.id AND " . approved_conference_where() . " AND c.end_date >= CURDATE()
            WHERE co.status = 'active'
            GROUP BY co.id
            HAVING conference_count > 0
            ORDER BY conference_count DESC, co.name ASC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_cities_with_counts(int $limit = 24): array
{
    $sql = "SELECT ci.*, co.name country_name, co.slug country_slug, COUNT(c.id) conference_count
            FROM cities ci
            JOIN countries co ON co.id = ci.country_id
            JOIN conferences c ON c.city_id = ci.id AND " . approved_conference_where() . " AND c.end_date >= CURDATE()
            WHERE ci.status = 'active' AND co.status = 'active'
            GROUP BY ci.id
            HAVING conference_count > 0
            ORDER BY conference_count DESC, ci.name ASC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_public_organizers(int $limit = 12): array
{
    $sql = "SELECT o.*, COUNT(c.id) conference_count
            FROM organizers o
            JOIN conferences c ON c.organizer_id = o.id AND " . approved_conference_where() . "
            JOIN users u ON u.id = o.user_id AND u.status = 'active'
            WHERE o.is_profile_complete = 1 AND o.is_suspended = 0
            GROUP BY o.id
            HAVING conference_count > 0
            ORDER BY o.is_featured DESC, o.is_verified DESC, conference_count DESC, o.created_at DESC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_approved_feedback(int $limit = 12): array
{
    $stmt = db()->prepare("SELECT * FROM user_feedbacks WHERE status = 'approved' ORDER BY is_verified DESC, created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_active_banners(int $limit = 5): array
{
    $stmt = db()->prepare("SELECT * FROM banners WHERE active = 1 AND status = 'approved' ORDER BY sort_order ASC, created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_categories(): array
{
    return db()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
}

function fetch_countries(): array
{
    return db()->query("SELECT * FROM countries WHERE status = 'active' ORDER BY name ASC")->fetchAll();
}

function fetch_cities(?int $countryId = null): array
{
    if ($countryId) {
        $stmt = db()->prepare("SELECT ci.*, co.name country_name FROM cities ci JOIN countries co ON co.id=ci.country_id WHERE ci.status='active' AND ci.country_id=? ORDER BY ci.name");
        $stmt->execute([$countryId]);
        return $stmt->fetchAll();
    }
    return db()->query("SELECT ci.*, co.name country_name FROM cities ci JOIN countries co ON co.id=ci.country_id WHERE ci.status='active' ORDER BY co.name, ci.name")->fetchAll();
}

function find_conference_by_slug(string $slug): ?array
{
    $stmt = db()->prepare("SELECT c.*, ca.name topic_name, ca.slug topic_slug, co.name country_name, co.slug country_slug,
                                 ci.name city_name, ci.slug city_slug, o.organization_name organizer_name,
                                 o.contact_person organizer_contact_person, o.slug organizer_slug, o.website organizer_public_website,
                                 o.logo organizer_logo, o.is_verified organizer_verified
                          FROM conferences c
                          LEFT JOIN categories ca ON ca.id=c.category_id
                          LEFT JOIN countries co ON co.id=c.country_id
                          LEFT JOIN cities ci ON ci.id=c.city_id
                          LEFT JOIN organizers o ON o.id=c.organizer_id
                          WHERE c.slug=? AND " . approved_conference_where() . " LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function find_public_organizer_by_slug(string $slug): ?array
{
    $stmt = db()->prepare("SELECT o.* FROM organizers o JOIN users u ON u.id=o.user_id WHERE o.slug=? AND o.is_profile_complete=1 AND o.is_suspended=0 AND u.status='active' LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}
