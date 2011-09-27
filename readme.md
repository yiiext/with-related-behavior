WithRelatedBehavior
===================

Это поведение позволяет проводить валидацию, вставку, обновление, а также сохранение модели и всех её связанных моделей. Поддерживаются все типы связей. Все запросы к СУБД автоматически оборачиваются в транзакции. Также поддерживается ручной вызов транзакций. Композитные ключи тоже поддерживаются.

Установка и настройка
---------------------

Скопируйте поведение в каталог extensions/wr вашего приложения и подключите в модели следующим образом:

```php
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

В моделях phpDoc тегами @var показаны реальные поля присутствующие в таблицах.

### Модель Post

```php
class Post extends CActiveRecord
{
	/**
	 * @var integer id
	 * @var integer author_id
	 * @var string title
	 * @var string content 
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
class Comment extends CActiveRecord
{
	/**
	 * @var integer id
	 * @var integer post_id
	 * @var string content
	 */
	...
}
```

### Модель Tag

```php
class Tag extends CActiveRecord
{
	/**
	 * @var integer id
	 * @var string name
	 */
	...
}
```

### Модель User

```php
class User extends CActiveRecord
{
	/**
	 * @var integer id
	 * @var string username
	 * @var string password
	 * @var string email
	 */
	...
	public function behaviors()
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
class Profile extends CActiveRecord
{
	/**
	 * @var integer user_id
	 * @var string firstname
	 * @var string lastname
	 */
	...
}
```

Формат параметра $data для всех методов
---------------------------------------

Этот параметр представляет собой ассоциативный массив, где в качестве значений указывается название атрибута, либо название связи.

```php
$post->save(array(
	'id','title',			// атрибуты модели
	'comments','tags'		// связи модели
));
```

При этом название связи можно указать также в виде ключа, значение которого — новый массив $data. Таким образом глубина данных вложенных массивов может быть бесконечной.

```php
$post->save(array(
	'comments'=>array(
		'id','content',		// атрибуты моделей связи comments
		'author',			// связь author в моделях связи comments
	),
));
```

**Примечание:** Если не указать атрибуты, то будут сохранены все. Для связей действует обратное правило. Будут сохранены модели только тех связей, которые явно указаны.

Использование
-------------

### Типы связей

#### HAS_ONE

```php
$user=new User;
$user->username='creocoder';
$user->email='creocoder@gmail.com';

$user->profile=new Profile;
$user->profile->firstname='Alexander';
$user->profile->lastname='Kochetov';

$user->withRelated->save(array('profile'));
```

#### HAS_MANY

```php
$post=new Post;
$post->title='Relational saving is not a dream now.';
$post->content='WithRelatedBehavior released...';

$comment1=new Comment;
$comment1->content='It was be hard.';
$comment2=new Comment;
$comment2->content='Yes, but we did this.';

$post->comments=array($comment1,$comment2);

$post->withRelated->save(array('comments'));
```

#### MANY_MANY

```php
$post=new Post;
$post->title='Relational saving is not a dream now.';
$post->content='WithRelatedBehavior released...';

$tag1=new Tag;
$tag1->name='relation';
$tag2=new Tag;
$tag2->name='save';

$post->tags=array($tag1,$tag2);

$post->withRelated->save(array('post'));
```

#### BELONGS_TO

```php
$post=new Post;
$post->title='Relational saving is not a dream now.';
$post->content='WithRelatedBehavior released...';

$post->author=new User;
$post->author->username='creocoder';
$post->author->email='creocoder@gmail.com';

$post->withRelated->save(array('author'));
```

**Примечание:** Как видно из примеров, вне зависимости от типа связи API остается одним и тем же. Также стоит отметить, что перед началом сохранения запускается транзакция, в случае если СУБД поддерживает эту возможность. При этом если транзакция начата пользователем самостоятельно к примеру в контроллере — поведение определяет это и не проводит старт транзакции.

Продвинутое использование
-------------------------

### Интеграция собственных стратегий обновления

В процессе написания.

### Сложный пример использования расширения

В процессе написания.

Временная документация (сложные примеры)
----------------------------------------

Будет удалена после дописания разделов основного руководства.

###Валидация

```php
<?php
$article=new Article;
$article->title='Test';

$comment1=new Comment;
$comment2=new Comment;
$comment3=new Comment;

$article->comments=array($comment1,$comment2,$comment3);

$comment1->content='Test';
$comment2->content='Test';
$comment3->content='Test';

$article->createdBy=new User;
$article->createdBy->name='Test';

$article->createdBy->group=new Group;
$article->createdBy->group->name='Test';

$article->withRelated->validate(array(
    'comments'=>array(
        'content'
    ),
    'createdBy'=>array(
        'name',
        'group'=>array(
            'name'
        )
    )
));
```

###Вставка

```php
<?php
$user=new User;
$user->name='Test';

$user->group=new Group;
$user->group->name='Test';

$tag1=new Tag;
$tag1->createdBy=$user;
$tag1->name='test1';
$tag2=new Tag;
$tag2->createdBy=$user;
$tag2->name='test2';
$tag3=new Tag;
$tag3->createdBy=$user;
$tag3->name='test3';

$article=new Article;
$article->title='Test';
$article->tags=array($tag1,$tag2,$tag3);

$comment1=new Comment;
$comment1->content='Test1';
$comment2=new Comment;
$comment2->content='Test2';
$comment3=new Comment;
$comment3->content='Test3';

$article->comments=array($comment1,$comment2,$comment3);

$article->createdBy=$user;

$article->withRelated->insert(array(
    'comments',
    'tags'=>array(
        'createdBy'
    ),
    'createdBy'=>array(
        'id','group_id','name',
        'group'=>array(
            'id','name'
        )
    )
));
```

###Обновление (пример 1)

```php
<?php
$article=Article::model()->findByPk(1);
$article->title='article1 updated';

$tag1=new Tag;
$tag1->name='tag1';
$tag2=new Tag;
$tag2->name='tag2';

$article->tags=array($tag1,$tag2);
$article->withRelated->update(array('tags'));
```

###Обновление (пример 2)

```php
<?php
$article=Article::model()->with('tags')->findByPk(1);
$article->title='article1 updated';

$tags=$article->tags;
$tags[0]->name='tag1 updated';
$tags[1]->name='tag2 updated';

$article->tags=$tags;
$article->withRelated->update(array('tags'));
```

**Примечание:** вместо методов insert() и update() можно пользоваться методом save() по аналогии с CActiveRecord.