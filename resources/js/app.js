import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

import App from './App.vue'
import PollPage from './pages/PollPage.vue'
import DisplayPage from './pages/DisplayPage.vue'
import MasterPage from './pages/MasterPage.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'poll', component: PollPage },
        { path: '/display', name: 'display', component: DisplayPage },
        { path: '/master', name: 'master', component: MasterPage },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
})

createApp(App).use(router).mount('#app')
