# Phel router

A data driver router for [Phel](https://phel-lang.org/).

* Based on [Symfony Routing](https://github.com/symfony/routing)
* Inspired by [reitit](https://github.com/metosin/reitit)
* Fast

## Installation

```bash
composer require phel-lang/router
```

## Route syntax

Routes are defined as vectors. The first element is the path of the route. This element is followed an optional map for route data and an optional vector of child routes. The paths of a route can have path parameters.

### Examples

Simple route:

```phel
["/ping"]
```

Two routes:

```phel
[["/ping"]
 ["/pong"]]
```

Routes with data:

```phel
[["/ping" {:name ::ping}]
 ["/pong" {:name ::pong}]]
```

Routes with path parameters:

```phel
[["/users/{user-id}"]
 ["/api/{version}/ping]]
```

Routes with path parameter validation:

```phel
["/users/{user-id<\d+>}"]
```

Routes with catch all parameters:

```phel
["/public/{path<.*>}"]
```

Nested routes:

```phel
["/api"
 ["/admin" {:middleware [admin-middleware-fn]}
  ["" {:name ::admin}]
  ["/db" {name ::db}]]
 ["/ping" {:name ::ping}]]
```

Same routes flattened:

```phel
[["/api/admin" {:middleware [admin-middleware-fn] :name ::admin}]
 ["/api/admin/db" {:middleware [admin-middleware-fn] :name ::db}]
 ["/api/ping" {:name ::ping}]]
```

## Router

Given a vector of routes a router can be create. Phel router offers two option to create a router. The first is a dynamic router that is evaluated with every new request:

```phel
(ns my-app
  (:require phel\router :as r))

(def router
  (r/router
    ["/api"
     ["/ping" {:name ::ping}]
     ["/user/{id}" {:name ::user}]]))
```

The second router is a compiled router. This router is evaluated during compile time (macro) and is therefore very fast. The drawback is that routes can not be created dynamically during the execution of a request.

```phel
(ns my-app
  (:require phel\router :as r))

(def router
  (r/compiled-router
    ["/api"
     ["/ping" {:name ::ping}]
     ["/user/{id}" {:name ::user}]]))
```

### Path based routing

To match a route given a path the `phel\router/match-by-path` function can be used. It takes a router and a path as arguments and returns a map or `nil`.

```phel
(r/match-by-path router "/api/user/10")
# Evaluates to
# {:template "/api/user/{id}"
#  :data {:name ::user
#  :path "/api/user/10"
#  :path-params {:id "10"}}}

(r/match-by-path router "/hello") # Evaluates to nil
```


### Name based routing

All routes that have `:name` route data can be matched by name using the `phel\router/match-by-name` function. It takes a router and the name of route and returns a map or `nil`.

```phel
(r/match-by-name router ::ping)
# Evaluates to
# {:template "/api/ping"
#  :data {:name ::ping}}

(r/match-by-name router ::foo) # Evaluates to nil
```

### Generate path

It is also possible to generate a path for a give route and it's path parameters. The function `phel\router/generate` takes a router, the name of the route and a map of router parameters. It returns either the generate path as string or throws an exception if the route can not be found ore path parameters are missing.

```phel
(r/generate router ::ping {}) # Evaluates to "/api/ping"

(r/generate router ::user {:id 10}) # Evaluates to "/api/user/10"

(r/generate router ::user {:id 10 :foo "bar"}) # Evaluates to "/api/user/10?foo=bar"

(r/generate router ::user {}) # Throws Symfony\Component\Routing\Exception\MissingMandatoryParametersException

(r/generate router ::foo {}) # Throws Symfony\Component\Routing\Exception\RouteNotFoundException
```

# Handler

Each route can have a handler functions that can are execute when a route matches the current path. A handler function is a function that takes as argument as `phel\http/request` and returns a `phel\http/response`.

```phel
# request -> response
(fn [request]
  (h/response-from-map {:status 200 :body "ok"}))
```

A handler can be placed either at the top level of the route data using the `:handler` keyword or under a specific method (`:get`, `:head`, `:patch`, `:delete`, `:options`, `:post`, `:put` or `:trace`). The top level handler is used if a request method based handler is not found.

```phel
[["/all" {:handler handler-fn}]
 ["/ping" {:name ::ping
           :get {:handler handler-fn}
           :post {:handler handler-fn}}]]
```

To process a request the router must be wrapped in the `phel\router/handler` method. This method returns a function that accepts a `phel\http/request` and returns a `phel\http/response`.

```phel
(ns my-app
  (:require phel\router :as r)
  (:require phel\http :as h))

(defn handler [req]
  (h/response-from-map {:status 200 :body "ok"}))

(def app
  (r/handler
    (r/router
      [["/all" {:handler handler}]
       ["/ping" {:name ::ping
                 :get {:handler handler}
                 :post {:handler handler}}]])))

(app (h/request-from-map {:method "DELETE" :uri "/all"}))
# Evaluates to (h/response-from-map  {:status 200 :body "ok"})

(app (h/request-from-map {:method "GET" :uri "/ping"}))
# Evaluates to (h/response-from-map {:status 200 :body "ok"})

(app (h/request-from-map {:method "PUT" :uri "/ping"}))
# Evaluates to (h/response-from-map {:status 404 :body "Not found"})
```

# Middleware

Each router can have multiple middleware functions. A middleware function is a function that takes a handler function and a `phel\http/request` and returns a `phel\http/response`.

```phel
# handler -> request -> response
(fn [handler request] (handler request))
```

A middleware can be placed either at the top level of the route data using the `:middleware` keyword or under a specific method (`:get`, `:head`, `:patch`, `:delete`, `:options`, `:post`, `:put` or `:trace`).

```phel
(ns my-app
  (:require phel\router :as r)
  (:require phel\http :as h))

(defn handler [req]
  (h/response-from-map
    {:status 200
     :body (push (get-in req [:attributes :my-middleware]) :handler)}))

(defn my-middleware [name]
  (fn [handler request]
    (handler
      (update-in
        request
        [:attributes :my-middleware]
        (fn [x]
          (if (nil? x)
            [name]
            (push x name)))))))

(def app
  (r/handler
    (r/router
      ["/api" {:middleware [(my-middleware :api)]}
       ["/ping" {:handler handler}]
       ["/admin" {:middleware [(my-middleware :admin)]}
        ["/db" {:middleware [(my-middleware :db)]
                :delete {:middleware [(my-middleware :delete)]
                         :handler handler}}]]])))

(app (h/request-from-map {:method "DELETE" :uri "/api/ping"}))
# Evaluates to (h/response-from-map {:status 200 :body [:api :handler]})

(app (h/request-from-map {:method "DELETE" :uri "/api/admin/db"}))
# Evaluates to (h/response-from-map {:status 200 :body [:api :admin :db :delete :handler]})
```
