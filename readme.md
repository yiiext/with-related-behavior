WithRelatedBehavior
===================

This behavior allows you to validate, insert, update and save a model along with
models from its relations. It supports all relation types. All DB queries are
wrapped into transactions automatically but there's a support for manual transaction
handling. Composite keys are supported as well.

Installation and configuration
------------------------------

Copy behavior to `extensions/wr` directory located inside your application and add
it to the model of your choice the following way:

```php
<?php
...
public function behaviors()
{
	return array(
		'withRelated'=>array(
			'class'=>'ext.wr.WithRelatedBehavior',
		),
	);
}
...
```

Models that will be used in this doc examples
---------------------------------------------

In the models below real DB fields are marked with `@property`.

### Post

```php
<?php
class Post extends CActiveRecord
{
	/**
	 * @property integer id
	 * @property integer author_id
	 * @property string title
	 * @property string content
	 */

	...
	public function relations()
	{
		return array(
			'author'=>array(self::BELONGS_TO,'User','author_id'),
			'comments'=>array(self::HAS_MANY,'Comment','article_id'),
			'tags'=>array(self::MANY_MANY,'Tag','post_tag(post_id,tag_id)'),
		);
	}
	...
}
```

### Comment

```php
<?php
class Comment extends CActiveRecord
{
	/**
	 * @property integer id
	 * @property integer post_id
	 * @property string content
	 */
	...
}
```

### Tag

```php
<?php
class Tag extends CActiveRecord
{
	/**
	 * @property integer id
	 * @property string name
	 */
	...
}
```

### User

```php
<?php
class User extends CActiveRecord
{
	/**
	 * @property integer id
	 * @property string username
	 * @property string password
	 * @property string email
	 */
	...
	public function relations()
	{
		return array(
			'profile'=>array(self::HAS_ONE,'Profile','user_id'),
		);
	}
	...
}
```

### Profile

```php
<?php
class Profile extends CActiveRecord
{
	/**
	 * @property integer user_id
	 * @property string firstname
	 * @property string lastname
	 */
	...
}
```

Format of the $data parameter for all methods where it's used
-------------------------------------------------------------

This parameter accepts an associative array where values are attribute or relation
names.

```php
<?php
$post->withRelated->save(true,array(
	'id','title',     // model attributes
	'comments','tags' // model relations
));
```

The name of the relation can be specified as a key. In this case its value is another
`$data` array with the same rules. So you there's no limit in how many times you can
nest these.

```php
<?php
$post->withRelated->save(true,array(
	'comments'=>array(
		'id','content', // comments relation attributes
		'author',       // author relation inside comments relation models
	),
));
```

**Note:** If you'll not specify any attributes, all attributes will be saved.
For relations it's the opposite: you should specify relations explicitly in order
for these to be saved.

Usage
-----

### Relation types

#### HAS_ONE

```php
<?php
$user=new User;
$user->username='creocoder';
$user->email='creocoder@gmail.com';

$user->profile=new Profile;
$user->profile->firstname='Alexander';
$user->profile->lastname='Kochetov';

$user->withRelated->save(true,array('profile'));
```

#### HAS_MANY

```php
<?php
$post=new Post;
$post->title='Relational saving is not a dream anymore.';
$post->content='Since WithRelatedBehavior released...';

$comment1=new Comment;
$comment1->content='Was it hard?';
$comment2=new Comment;
$comment2->content='Yes, but we made it.';

$post->comments=array($comment1,$comment2);

$post->withRelated->save(true,array('comments'));
```

#### MANY_MANY

```php
<?php
$post=new Post;
$post->title='Relational saving is not a dream anymore.';
$post->content='Since WithRelatedBehavior released...';

$tag1=new Tag;
$tag1->name='relation';
$tag2=new Tag;
$tag2->name='save';

$post->tags=array($tag1,$tag2);

$post->withRelated->save(true,array('tags'));
```

#### BELONGS_TO

```php
<?php
$post=new Post;
$post->title='Relational saving is not a dream anymore.';
$post->content='Since WithRelatedBehavior released...';

$post->author=new User;
$post->author->username='creocoder';
$post->author->email='creocoder@gmail.com';

$post->withRelated->save(true,array('author'));
```

**Note:** As you can see, API stays the same no matter which relation type is used.
Also it worth mentioning that a transaction is started before saving if DB supports it.
If transaction was already started manually, behavior detects it and doesn't
start its own transaction. By default, same as `CActiveRecord::save()` does,
`WithRelatedBehavior::save()` validates data and starts saving it only if all
models it's going to save are valid. You can disable validation by passing `false`
to `$runValidation` parameter.

Recursive composite validation
------------------------------

As opposed to standard `CModel::validate()` method, `WithRelatedBehavior::validate()`
does composite model validation. That means it validates all related models as
well. Validation result is returned as a boolean value. If any of the models is not valid
than result will be `false`. If all models are valid than result will be `true`.
Additionally you can limit validation to model attributes as follows:

```php
<?php
$post=new Post;
$post->title='Relational validation is not a dream anymore.';
$post->content='Since WithRelatedBehavior released...';

$comment1=new Comment;
$comment1->content='Was it hard?';
$comment2=new Comment;
$comment2->content='Yes, but we made it.';

$post->comments=array($comment1,$comment2);

$result=$post->withRelated->validate(array(
	'title',		// only `title` attribute of the Post model will be validated
	'comments'=>array(
		'content',	// only `content` attribute of the Comment model will be validated
	),
));
```

Advanced usage
--------------

### Using custom update strategies

TBD.

### An advanced usage example

```php
<?php
$post=new Post;
$post->title='Post title';
$post->content='Post content';

$user=new User;
$user->username='someuser';
$user->email='someuser@example.com';

$user->profile=new Profile;
$user->profile->firstname='Vasya';
$user->profile->lastname='Pupkin';

$post->author=$user;

$comment1=new Comment;
$comment1->author=$user;
$comment1->content='Some content 1';
$comment2=new Comment;
$comment2->author=$user;
$comment2->content='Some content 2';

$post->comments=array($comment1,$comment2);

$tag1=new Tag;
$tag1->name='tag1';
$tag2=new Tag;
$tag2->name='tag2';

$post->tags=array($tag1,$tag2);

$post->withRelated->save(true,array(
	'author'=>array(
		'profile',
	),
	'comments'=>array(
		'author',
	),
	'tags',
));
```

In order to save `post` and related models an extension builds saving plan first.
In the example above that before saving we need to save `user` model and its related
`profile`. After doing it we'll be able to save `post`. Then goes `comments`
(`author` is the same `user`). Last `tags` is saved. Actions mentioned are executed
inside a transaction. Extension takes care about all these.