import Vue from 'vue';
import VueRouter from 'vue-router';
import index from './views/front/index.vue';
import home from './views/front/home.vue';
import confectionery from './views/front/confectionery/index.vue';
import menu from './views/front/confectionery/menu.vue';
import list from './views/front/confectionery/list.vue';
import order from './views/front/order-confirm.vue';
import aboutus from './views/front/about-us.vue';
import contactus from './views/front/contact-us.vue';
import conditions from './views/front/terms-conditions.vue';
import faq from './views/front/faq.vue';
import profile from './views/front/user/profile.vue';
import store from './store.js';
import purchase from './views/front/order-purchased.vue';
import add_comment from './views/front/user/addComment.vue';
import cooperation from './views/front/cooperation.vue';
import EventBus from './components/eventBus.vue';

Vue.use(VueRouter);

// Define some routes
const routes = [
    { path: '/', component: index ,
        children:[
            {
                path: '', name: 'home', component: home
            },
            {
                path: '/order-confirm', name: 'order-confirm', component: order,
                beforeEnter: (to, from, next) => {

                    let vm = this;
                    store.state.loggedIn().then(function(){
                        next()
                    }).catch(function(){
                        var current_name = router.history.current.name,
                            current_url = router.history.current.params.id;
                        router.push({path: current_name, params: { id: current_url }})
                        // push({path: '/'});
                    })
                }
            },
            {
                path: '/about-us', name: 'about-us', component: aboutus
            },
            {
                path: '/contact-us', name: 'contact-us', component: contactus
            },
            {
                path: '/cooperation', name: 'cooperation', component: cooperation
            },
            {
                path: '/terms-conditions', name: 'terms-conditions', component: conditions
            },
            {
                path: '/profile', name: 'profile', component: profile,
                beforeEnter: (to, from, next) => {

                    let vm = this;
                    store.state.loggedIn().then(function(){
                        next()
                    }).catch(function(){
                        router.push({path: '/'});
                        window.location.reload();
                    })
                }
            },
            {
                path: '/faq', name: 'faq', component: faq
            },
            {
                path: '/order-purchased', name: 'order-purchased', component: purchase
            },
            {
                path: 'user/order/:id', name: 'user-order',component: purchase,
                beforeEnter: (to, from, next) => {
                    store.state.pay_type = false;
                    next();
                }
            },
            {
                path: 'user/order/:id/add-comment', name: 'add-comment',component: add_comment,
                beforeEnter: (to, from, next) => {

                    let vm = this;
                    store.state.loggedIn().then(function(){
                        next()
                    }).catch(function(){
                        router.push({path: '/'});
                        window.location.reload();
                    })
                }
            },
            { path: '/confectionery', component: confectionery,
                children:[
                    {
                        path: 'menu/:id', name: 'menu', component: menu
                    },
                    {
                        path: '', name: 'confectionery', component: list
                    }
                ]
            },
        ]
    },
    { path: '*', redirect: {name: 'home'} }
]

// Create the router instance and pass the `routes` option
const router = new VueRouter({
    mode: 'history',
    keepAlive: true,
    routes
});

router.afterEach((to, from) => {
    $('html, body').animate({scrollTop : 0},800);
})




//
// router.beforeEach(function(to, from, next){
//
//
// });

// router.redirect({
//     '*': '/'
// });

export default router;