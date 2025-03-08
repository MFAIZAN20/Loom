-- Loom seed data (optional)
-- Import AFTER `database/schema.sql`.

INSERT INTO `categories` (`category_id`, `name`, `description`, `slug`, `parent_id`, `icon`, `display_order`, `is_active`)
VALUES
  (1, 'General Discussion', 'General topics and conversations', 'general-discussion', NULL, 'fa-comments', 1, 1),
  (2, 'Technology', 'Discussions about technology, gadgets, and software', 'technology', NULL, 'fa-laptop', 2, 1),
  (3, 'Health & Wellness', 'Topics related to health, fitness, and wellness', 'health-wellness', NULL, 'fa-heartbeat', 3, 1),
  (4, 'Arts & Entertainment', 'Music, movies, art, and other entertainment topics', 'arts-entertainment', NULL, 'fa-film', 4, 1),
  (5, 'Science', 'Scientific discussions and discoveries', 'science', NULL, 'fa-flask', 5, 1),
  (6, 'Education', 'Learning resources and academic discussions', 'education', NULL, 'fa-graduation-cap', 6, 1),
  (7, 'Advice & Help', 'Ask for advice or offer help to others', 'advice-help', NULL, 'fa-life-ring', 7, 1),
  (8, 'Hobbies', 'Share and discuss your hobbies and interests', 'hobbies', NULL, 'fa-puzzle-piece', 8, 1),
  (9, 'News & Current Events', 'Discuss news and current events', 'news-events', NULL, 'fa-newspaper', 9, 1),
  (10, 'Off-Topic', 'Discussions that dont fit in other categories', 'off-topic', NULL, 'fa-random', 10, 1),
  (11, 'Programming', 'Programming languages, development, and coding', 'programming', 2, 'fa-code', 1, 1),
  (12, 'Web Development', 'Web design, development, and technologies', 'web-development', 2, 'fa-globe', 2, 1),
  (13, 'Mobile Apps', 'Mobile application development and discussions', 'mobile-apps', 2, 'fa-mobile-alt', 3, 1),
  (14, 'Hardware', 'Computer hardware, components, and peripherals', 'hardware', 2, 'fa-microchip', 4, 1);

