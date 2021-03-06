# EntityGenerator by CodeAge

## About

I wrote this bundle to simplify my life when creating new Entity in **Symfony2** project. I was using architecture of _"3 layers"_ which split your ORM logic into few files:
 * **Model** - Contains only fields data and its getters and setters (and add, or remove if needed :D )
 * **Entity** - Main file that is used to create Entity object, this file store internal Entity logic. eg. when you want to modify your data in some way.
 * **Manager** - And the Manager file which should be treated as _"Repository"_ of given Entity and you should put here every QueryBuilder that concerns it.

For now this bundle supports mapping only in **XML** format. I think it's good practice to separate all these layers to improve readability of your code.

What is more important, every Manager gets it own Service so you are able to call it in easy way. More about it in **Usage** section.

## Installation

Easy as pie :) Add to your **composer.json** file in require section:
```json
"codeage/entitygenerator": "1.*"
```
And update your vendors!

Now the only thing left to do is enable this bundle in your **AppKernel** file, to do it, add:
```php
new CodeAge\EntityGeneratorBundle\EntityGeneratorBundle(),
```

***
As of version **1.1.0** traits were added to AbstractEntity in order to use methods (that I will add someday :) ) even when you cannot extend AbstractEntity for some reason, eg. if you are using FOSUserBundle and you need to extend their User Entity.
***

## Usage
To create new entity use command below:
```bash
$ php app/console ca:entity:generate

#for Symfony3
$ php bin/console ca:entity:generate
```

Then you will be asked about name of your new entity, but you need to proceed it with bundle name in which it will be created (Command should autocomplete name of bundle :) ). eg.
```bash
UserBundle:User
```
Where **UserBunlde** is bundle name and **User** is your new entity name.

From now on, you will be asked to provide field name, its type (default is string) and its parameters. String for example can be configured with length and nullable value. 

There is also _"entity"_ type which is used to create relation field. (**Note:** For now only one-direction relations are available)

When you add all needed fields you will be asked to confirm generation of your entity, and several files will be created in your bundle directory (as described in **about** section :) )

In your controller you can now call your manager in this way:
```php
function indexAction($id){
    $userManager = $this->get(UserManager::SERVICE); /* @var $userManager UserManager */
    $user = $userManager->find($id); /* @var $user User */
}
```
And that's it! As I said before, manager is used just like repository, so you can create some usefull queries in there to use insetad of "find" like in example here, but you can also use all respoitory-like methods as well eg.
 * findBy
 * findAll
 * findOneBy


And what about insertions, updates and deletes? 
#### Insert
```php
$userManager = $this->get(UserManager::SERVICE); /* @var $userManager UserManager */
$user = new User();
$user->setName('SomeNameOFUser');

$userManager->persist($user)
       ->flush();
```
#### Update
```php
$userManager = $this->get(UserManager::SERVICE); /* @var $userManager UserManager */
$user = $userManager->find($id); /* @var $user User */
//Assume that there is a user with this id :)

$user->setName('NewNameForUser');
$userManager->update($user); //Done! But you can pass false as second parameter to disable force-flush :)
```
#### Delete/Remove
```php
$userManager = $this->get(UserManager::SERVICE); /* @var $userManager UserManager */
$user = $userManager->find($id); /* @var $user User */
$userManager->remove($user); //Done! But you still can disable force-flush
```

### Planned changes:

 * [x] ~~Use **traits** instead of simple inheritance, for simplifying implementation of FOSUserBundle :)~~
 * [x] ~~Printing list of all possible types for field~~
 * [x] ~~Print all entity configuration when asking if user confirm generation~~
 * [ ] Ability to modify field after adding it
 * [ ] Get all types from DBAL
 * [ ] Enum types generator
