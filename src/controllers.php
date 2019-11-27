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

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user) {
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
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

    $sql = "SELECT * FROM todos WHERE id = '$id'";
    $todo = $app['db']->fetchAssoc($sql);
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
    $sqlTotalPage = "SELECT count(*) as total FROM todos where user_id = '${user['id']}'";
    $totalTodos = $app['db']->fetchAll($sqlTotalPage);
    $totalPages = ceil($totalTodos[0]['total'] / $defaultPageSize);
    if ($currentPage > $totalPages) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Page number is illegl!'
        ]);
        return $app->redirect('/todo');
    }

    // get data
    $offset = ($currentPage - 1) * $defaultPageSize;
    $sql = "SELECT * FROM todos where user_id = '${user['id']}' limit " . $offset . "," . "$defaultPageSize";
    $todos = $app['db']->fetchAll($sql);
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
        return $app->redirect("/todo");
    }

    $sql = "SELECT * FROM todos WHERE id = '$id'";
    $todo = $app['db']->fetchAssoc($sql);

    if (empty($todo)) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Id not exists!'
        ]);
        return $app->redirect('/todo');
    }

    return $app['twig']->render('todo.html', [
        'todo' => $todo,
    ]);
});

$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    if (empty($description)) {
        $app['session']->getFlashBag()->add('message', [
            'type' => $app['ERROR'],
            'info' => 'Description is empty!'
        ]);
        return $app->redirect('/todo');
    }

    $app['session']->getFlashBag()->add('message', [
        'type' => $app['SUCCESS'],
        'info' => 'Cool, Add todo successfully!'
    ]);

    $sql = "INSERT INTO todos (user_id, description) VALUES ('$user_id', '$description')";
    $app['db']->executeUpdate($sql);

    return $app->redirect('/todo');
});

$app->post('/todo/finish/{id}', function ($id) use ($app) {
    if ($app['session']->get('user') === null) {
        return $app->redirect('/login');
    }
    $sql = "UPDATE todos SET status = 1 WHERE id = '$id'";
    $app['db']->executeUpdate($sql);
    return $app->redirect('/todo');
});

$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $sql = "DELETE FROM todos WHERE id = '$id'";
    $app['db']->executeUpdate($sql);
    $app['session']->getFlashBag()->add('message', [
        'type' => $app['SUCCESS'],
        'info' => 'Delete todo successfully!'
    ]);

    return $app->redirect('/todo');
});