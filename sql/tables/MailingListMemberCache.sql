create table MailingListMemberCache (
	id serial,
	email varchar(255) not null,

	instance integer references Instance(id) on delete cascade,

	primary key (id)
);

create index MailingListMemberCache_email_index on MailingListMemberCache(email);
