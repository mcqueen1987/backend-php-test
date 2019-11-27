<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});

$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if (!empty($username)) {
        $entityManager = $app['orm.em'];
        $user = $entityManager->getRepository('\Module\User')->findOneBy([
            'username' => $username,
            'password' => $password
        ]);
        if (!empty($user)) {
            $app['session']->set('user', [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'password' => $user->getPassword()
            ]);
            return $app->redirect('/todo');
        }
    }
    return $app['twig']->render('login.html', []);
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});

$app->get('/todo/{id}/json', function ($id) use ($app) {
    if ($app['session']->get('user') === null) {
        return $app->redirect('/login');
    }

    // id should be int and start from 1
    if (empty($id) || strval($id) !== strval(intval($id)) || $id < 1) {
        return $app->redirect("/todo");
    }

    $entityManager = $app['orm.em'];
    $todo = $entityManager->getRepository('\Module\Todo')->find($id);
    return $app->json($todo);
});

$app->get('/todo', function () use ($app) {
    $user = $app['session']->get('user');
    if ($user === null) {
        return $app->redirect('/login');
    }

    // get total page
    $defaultPageSize = 5;
    $currentPage = isset($_GET['page']) ? $_GET['page'] : 1;
    $repository = $app['orm.em']->getRepository('\Module\Todo');
    $totalTodos = count($repository->findBy([
        'user_id' => $user['id']
    ]));
    $totalPages = ceil($totalTodos / $defaultPageSize);
    if ($currentPage > $totalPages) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Page number is illegl!'
        ]);
        return $app->redirect('/todo');
    }

    // get todos
    $offset = ($currentPage - 1) * $defaultPageSize;
    $todos = $repository->findBy(
        ['user_id' => $user['id']],
        ['id' => 'DESC'],
        $defaultPageSize,
        $offset
    );

    return $app['twig']->render('todos.html', [
        'todos' => $todos,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages
    ]);
});

$app->get('/todo/{id}', function ($id) use ($app) {
    if ($app['session']->get('user') === null) {
        return $app->redirect('/login');
    }

    // id should be int and start from 1
    if (empty($id) || strval($id) !== strval(intval($id)) || $id < 1) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Id does not exists!'
        ]);
        return $app->redirect("/todo");
    }

    // get todoInfo
    $repository = $app['orm.em']->getRepository('\Module\Todo');
    $todo = $repository->find($id);

    if (empty($todo)) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Id does not exists!'
        ]);
        return $app->redirect('/todo');
    }

    return $app['twig']->render('todo.html', [
        'todo' => $todo,
    ]);
});

$app->post('/todo/add', function (Request $request) use ($app) {
    $user = $app['session']->get('user');
    if ($user === null) {
        return $app->redirect('/login');
    }

    $description = $request->get('description');
    if (empty($description)) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Description is empty!'
        ]);
        return $app->redirect('/todo');
    }

    // save data
    $entityManager = $app['orm.em'];
    $todoObj = new \Module\Todo();
    $todoObj->setUserId($user['id'])
        ->setDescription($description)
        ->setStatus(0);
    $entityManager->persist($todoObj);
    $entityManager->flush();
    $app['session']->getFlashBag()->add('message', [
        'type' => $app['SUCCESS'],
        'info' => 'Cool, Add todo successfully!'
    ]);
    return $app->redirect('/todo');
});

$app->post('/todo/finish/{id}', function ($id) use ($app) {
    if ($app['session']->get('user') === null) {
        return $app->redirect('/login');
    }
    $entityManager = $app['orm.em'];
    $todoObj = $entityManager->find('\Module\Todo', $id);
    $todoObj->setStatus(1);
    $entityManager->persist($todoObj);
    $entityManager->flush();
    $app['session']->getFlashBag()->add('message', [
        'type' => 'Succeed',
        'info' => 'Update todo as finished successfully!'
    ]);
    return $app->redirect('/todo');
});

$app->match('/todo/delete/{id}', function ($id) use ($app) {
    // id should be int and start from 1
    if (empty($id) || strval($id) !== strval(intval($id)) || $id < 1) {
        return $app->redirect("/todo");
    }

    $entityManager = $app['orm.em'];
    $todoObj = $entityManager->find('\Module\Todo', $id);
    $entityManager->remove($todoObj);
    $entityManager->flush();

    $app['session']->getFlashBag()->add('message', [
        'type' => $app['SUCCESS'],
        'info' => 'Delete todo successfully!'
    ]);

    return $app->redirect('/todo');
});