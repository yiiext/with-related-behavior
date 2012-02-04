WithRelatedBehavior
===================

Это поведение позволяет проводить валидацию, вставку, обновление, а также
сохранение модели и всех её связанных моделей. Поддерживаются все типы связей.
Все запросы к СУБД автоматически оборачиваются в транзакции. Также поддерживается
ручной вызов транзакций. Композитные ключи тоже поддерживаются.

Установка и настройка
---------------------

Скопируйте поведение в каталог `extensions/wr` вашего приложения и подключите
в модели следующим образом:

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

Модели используемые в примерах
------------------------------

В моделях phpDoc тегами `@property` показаны реальные поля присутствующие в таблицах.

### Модель Post

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

### Модель Comment

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

### Модель Tag

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

### Модель User

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

### Модель Profile

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

Формат параметра $data для всех методов
---------------------------------------

Этот параметр представляет собой ассоциативный массив, где в качестве значений
указывается название атрибута, либо название связи.

```php
<?php
$post->withRelated->save(true,array(
	'id','title',     // атрибуты модели
	'comments','tags' // связи модели
));
```

При этом название связи можно указать в виде ключа, значение которого — новый
массив `$data`. Таким образом, глубина данных вложенных массивов может быть бесконечной.

```php
<?php
$post->withRelated->save(true,array(
	'comments'=>array(
		'id','content', // атрибуты моделей связи comments
		'author',       // связь author в моделях связи comments
	),
));
```

**Примечание:** Если не указать атрибуты, то будут сохранены все. Для связей
действует обратное правило. Будут сохранены модели только тех связей, которые
указаны явно.

Использование
-------------

### Типы связей

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

$post->withRelated->save(true,array('post'));
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

**Примечание:** Как видно из примеров, вне зависимости от типа связи API остается
неизменным. Стоит отметить, что перед началом сохранения запускается транзакция,
в случае если СУБД поддерживает эту возможность. Если транзакция уже начата
пользователем самостоятельно, к примеру в контроллере, — поведение определяет это
и не проводит повторный старт транзакции. По умолчанию, по аналогии с методом
`CActiveRecord::save()`, метод `WithRelatedBehavior::save()` также проводит валидацию
и сохранение происходит только в том случае, если все модели, подготовленные для записи,
валидны. Это можно изменить выставив параметр `$runValidation` метода в `false`.

Комплексная рекурсивная валидация
---------------------------------

В отличие от штатного `CModel::validate()`, метод `WithRelatedBehavior::validate()`
проводит комплексную валидацию модели и всех связанных моделей. Результат валидации
возвращается в виде булева значения. В том случае, если хотя-бы одна из моделей,
принимающих участие в валидации, невалидна, результат работы метода — `false`.
Если же все модели валидны, результат работы — `true`. Возможно ограничение валидации
по атрибутам моделей. Это показано в следующем примере:

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
	'title',		// будет проверен только атрибут `title` модели Post
	'comments'=>array(
		'content',	// будет проверен только атрибут `content` модели Comment
	),
));
```

Продвинутое использование
-------------------------

### Интеграция собственных стратегий обновления

В процессе написания.

### Сложный пример использования расширения

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

Для того, чтобы сохранить `post` и связанные модели, расширение составляет
план сохранения. В данном примере видно, что перед началом сохранения необходимо
вначале сохранить модель `user` и связанную с ней модель `profile` и только затем
появится возможность сохранить `post`, затем `comments` (при этом `author` всё тот же `user`),
после чего сохраняются `tags`. Сохранение всех моделей оборачивается в транзакцию.
Все перечисленные действия ложатся на плечи расширения.
