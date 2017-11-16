DROP TABLE IF EXISTS groups_contacts;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS groups;

CREATE TABLE contacts (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    username TEXT(50) NOT NULL UNIQUE,
    created TEXT(19) NOT NULL,
    modified TEXT(19) NOT NULL
);

CREATE TABLE groups (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    name TEXT(50) NOT NULL UNIQUE,
    created TEXT(19) NOT NULL,
    modified TEXT(19) NOT NULL
);

CREATE TABLE groups_contacts (
    group_id INTEGER NOT NULL,
    contact_id INTEGER NOT NULL,
    created TEXT(19) NOT NULL,
    modified TEXT(19) NOT NULL,
    PRIMARY KEY (group_id, contact_id),
    FOREIGN KEY(group_id) REFERENCES groups(id),
    FOREIGN KEY(contact_id) REFERENCES contacts(id)
);
