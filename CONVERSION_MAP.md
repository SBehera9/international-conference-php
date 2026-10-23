# React → PHP Conversion Map

The supplied React/TypeScript project is used as the functional reference, not as a pixel-identical UI reference.

| Functional area | PHP implementation |
|---|---|
| Public Home | `index.php` |
| Conference listing / search / filters | `conferences.php` |
| Conference detail | `conference.php` |
| Registration redirect / click tracking | `redirect.php` |
| Organizer directory | `organizers.php` |
| Organizer public profile | `organizer-public.php` |
| Media Partner form + directory | `media-partner.php` |
| Associates form + directory | `our-associates.php` |
| About | `about-us.php` |
| Contact | `contact-us.php` |
| Feedback | `feedback.php` |
| Unified Login | `login.php` |
| Organizer registration | `organizer-signup.php` |
| Organizer profile setup | `organizer/profile.php` |
| Organizer dashboard | `organizer/index.php` |
| Add/edit conference | `organizer/add-conference.php` |
| Organizer workflow lists | `organizer/*-conferences.php` |
| Admin dashboard | `admin/index.php` |
| Approve/reject | `admin/pending-conferences.php` |
| Admin conference management | `admin/manage-conferences.php` |
| Organizer administration | `admin/organizers.php` |
| Partner/associate moderation | `admin/media-partners.php`, `admin/associates.php` |
| Topics | `admin/topics.php` |
| Countries/cities/timezones | `admin/locations.php` |
| Feedback moderation | `admin/feedback.php` |
| Blog management | `admin/blog.php`, `admin/blog-editor.php` |
| Subscribers | `admin/subscribers.php` |
| Site copy/content | `admin/content.php` |
| Admin profile | `admin/profile.php` |
| Database layer | `includes/database.php` + MySQL PDO |
| Authentication/roles | `includes/auth.php` |
| Reusable UI | `includes/header.php`, `footer.php`, dashboard includes/components |
| SEO helpers / JSON-LD | `includes/seo.php` |
| Dynamic sitemap | `sitemap.php` → `/sitemap.xml` |
| Dynamic robots | `robots.php` → `/robots.txt` |
| Database schema | `database/database.sql` |

## Preserved logic rules

- Admin and Organizers share one Login.
- Server redirects each role to its own dashboard.
- Organizer must complete profile setup after signup.
- Organizer submissions require Admin approval before public publication.
- Admin controls Featured Conferences.
- Home Topics/Countries/Cities are derived from published conferences instead of showing unused taxonomy entries.
- Home organizer profiles only show organizers with published conferences.
- Approved conference content is protected from organizer modification.
- Rejected conferences retain rejection feedback and can be edited/resubmitted.
- Completed conferences are derived from end date.
- Media Partners, Associates and Feedback use moderation before public display.
- Public conference pages use unique slugs and SEO metadata.