create table MailingListUnsubscribeQueue (
	id serial,
	email varchar(255) not null,

	instance integer references Instance(id) on delete cascade,

	primary key (id)
);

create index MailingListUnsubscribeQueue_email_index on MailingListUnsubscribeQueue(email);
