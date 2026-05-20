<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed, watchEffect  } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

// Heroicons
import {
  HomeIcon, DocumentCurrencyYenIcon,
  UserIcon, DocumentIcon,DocumentTextIcon,
  ServerIcon,
  UsersIcon,
  PlusIcon,
  BuildingOfficeIcon,
  TicketIcon,
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
  CubeIcon,
  BeakerIcon,Bars3Icon, XMarkIcon,
  ShieldCheckIcon, // ← 追加
} from '@heroicons/vue/24/outline'

const page = usePage()

const mobileOpen = ref(false)       // モバイル用の開閉状態

const collapsed = ref(false)

const openMenu = ref(null)
const openChildMenu = ref(null)

const toggleMenu = (key) => {
  openMenu.value =
    openMenu.value === key ? null : key
}

const toggleChildMenu = (key) => {
  openChildMenu.value =
    openChildMenu.value === key ? null : key
}

const { t, locale } = useI18n()

const menus = [
  {
    key: 'exams',
    label: '指導士資格認定試験',
    icon: UsersIcon,

    children: [
      {
        key: 'exam_manage',
        label: '自験例報告',

        children: [
          {
            label: t('exams.reports_list'),
            route: 'reports.index',
          },
          {
            label: t('exams.reports'),
            route: 'reports.create',
          },
        ],
      },

      {
        key: 'exam_apply',
        label: t('exams.application'),

        children: [
          {
            label: t('applications.create'),
            route: 'exams.create',
          },
        ],
      },
    ],
  },

  {
    key: 'instructors',
    label: '指導士認定更新',
    icon: DocumentIcon,

    children: [
      {
        label: '更新申請',
        route: 'pdf-uploads.create',
      },
      {
        label: '単位取得申請',
        route: 'pdf-uploads.index',
      },
    ],
  },

  {
    key: 'annual_fees',
    label: '年会費支払い状況',
    icon: DocumentCurrencyYenIcon,

    children: [
      {
        label: '年会費一覧',
        route: 'annual-fees.index',
      },
    ],
  },
]

const { props } = usePage()
console.log(props)
// Jetstream props
const authUser = props.auth.user
const currentTeam = authUser.current_team
const currentTeamId = authUser.current_team_id
const allTeams = authUser.all_teams
const hasApiFeatures = props.jetstream.hasApiFeatures
const hasTeamFeatures = props.jetstream.hasTeamFeatures
const canCreateTeams = props.jetstream.canCreateTeams

watchEffect(() => {

  menus.forEach((menu) => {

    menu.children.forEach((child) => {

      if (child.children) {

        child.children.forEach((sub) => {

          const target = route(sub.route)

          if (page.url.startsWith(new URL(target).pathname)) {

            openMenu.value = menu.key
            openChildMenu.value = child.key

          }

        })

      } else {

        const target = route(child.route)

        if (page.url.startsWith(new URL(target).pathname)) {

          openMenu.value = menu.key

        }

      }

    })

  })

})
// ヘッダー操作
const logout = () => { router.post(route('logout')) }
const switchTeam = (team) => { router.put(route('current-team.update'), { team_id: team.id }) }
const isActive = (name) => route().current(name)

// ---------------------------
// Permission helper (Vue側)
// ---------------------------
const user = usePage().props.auth.user || null

console.log(user)

const can = (permissionName) => {
  if (!user) return false

  // permissions を安全に配列化
  const perms = Array.isArray(user.permissions)
    ? user.permissions
    : (user.permissions?.data ?? [])
  if (perms.length > 0) {
    return perms.some(p => p.name === permissionName)
  }

  // role を配列化
  const roles = Array.isArray(user.roles) ? user.roles : (user.roles?.data ?? [])

  if (roles.length > 0) {
    // Super Admin は全権限
    if (roles.some(r => ['super admin', 'super-admin'].includes(r.name.toLowerCase()))) {
      return true
    }

    // Tenant Admin は一部権限のみ
    if (roles.some(r => r.name.toLowerCase().startsWith('tenant_admin'))) {
      return ['manage roles', 'manage permissions'].includes(permissionName)
    }
  }

  return false
}

// showAccessControl : セクション丸ごと表示判定
const showAccessControl = computed(() => {
  if (!user) return false

  // Super Admin は全て表示
  if (user.roles?.some(r => r.name.toLowerCase() === 'super admin')) {
    return true
  }

  // テナント管理者は role / permission のみ表示
  return can('manage roles') || can('manage permissions')
})
</script>

<template>
  <div class="flex">
    <!-- モバイル用ハンバーガー -->
    <button
      @click="mobileOpen = !mobileOpen"
      class="lg:hidden p-2 rounded-full hover:bg-gray-200"
    >
      <template v-if="mobileOpen">
        <XMarkIcon class="w-5 h-5 text-gray-600" />
      </template>
      <template v-else>
        <Bars3Icon class="w-5 h-5 text-gray-600" />
      </template>
    </button>

    <!-- サイドバー -->
    <aside
      :class="[
        'bg-gray-100 h-screen flex flex-col transition-all duration-300 z-50',
        collapsed ? 'w-16' : 'w-64',
        mobileOpen ? 'left-0' : '-left-full',
        'fixed top-0 lg:relative lg:left-0 h-screen'
      ]"
    >
      <!-- PC折りたたみボタン -->
      <div class="flex justify-end p-2 flex-none lg:flex">
        <button
          @click="toggleCollapse"
          class="p-2 rounded-full hover:bg-gray-200"
        >
          <template v-if="collapsed">
            <Bars3Icon class="w-5 h-5 text-gray-600" />
          </template>
          <template v-else>
            <XMarkIcon class="w-5 h-5 text-gray-600" />
          </template>
        </button>
      </div>

       <nav class="flex-1 overflow-y-auto px-2 py-4 text-sm">
            <!-- Dashboard -->
        <Link :href="route('dashboard')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('dashboard') ? 'bg-gray-300 font-semibold' : ''">
          <HomeIcon class="w-5 h-5"/>
          <span v-if="!collapsed" class="ml-2">{{ t('dashboard') }}</span>
        </Link>

        <div class="mt-2">

        <div
          v-for="menu in menus"
          :key="menu.key"
          class="mb-1"
        >

          <!-- 親 -->
          <button
            @click="toggleMenu(menu.key)"
            class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200"
          >

            <div class="flex items-center">

              <component
                :is="menu.icon"
                class="w-5 h-5"
              />

              <span
                v-if="!collapsed"
                class="ml-2"
              >
                {{ menu.label }}
              </span>

            </div>

            <svg
              v-if="!collapsed"
              class="w-4 h-4 transition-transform"
              :class="{
                'rotate-90': openMenu === menu.key
              }"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 5l7 7-7 7"
              />
            </svg>

          </button>

          <!-- 2階層 -->
          <transition name="slide-fade">

            <div
              v-show="openMenu === menu.key && !collapsed"
              class="pl-4 mt-1 space-y-1"
            >

              <div
                v-for="child in menu.children"
                :key="child.key || child.label"
              >

                <!-- 3階層あり -->
                <template v-if="child.children">

                  <button
                    @click="toggleChildMenu(child.key)"
                    class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200"
                  >

                    <span>{{ child.label }}</span>

                    <svg
                      class="w-4 h-4 transition-transform"
                      :class="{
                        'rotate-90': openChildMenu === child.key
                      }"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 5l7 7-7 7"
                      />
                    </svg>

                  </button>

                  <!-- 3階層 -->
                  <transition name="slide-fade">

                    <div
                      v-show="openChildMenu === child.key"
                      class="pl-6 mt-1 space-y-1"
                    >

                      <Link
                        v-for="sub in child.children"
                        :key="sub.route"
                        :href="route(sub.route)"
                        class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
                        :class="isActive(sub.route)
                          ? 'bg-gray-300 font-semibold'
                          : ''"
                      >

                        <CubeIcon class="w-5 h-5"/>

                        <span
                          v-if="!collapsed"
                          class="ml-2"
                        >
                          {{ sub.label }}
                        </span>

                      </Link>

                    </div>

                  </transition>

                </template>

                <!-- 2階層のみ -->
                <template v-else>

                  <Link
                    :href="route(child.route)"
                    class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
                    :class="isActive(child.route)
                      ? 'bg-gray-300 font-semibold'
                      : ''"
                  >

                    <CubeIcon class="w-5 h-5"/>

                    <span
                      v-if="!collapsed"
                      class="ml-2"
                    >
                      {{ child.label }}
                    </span>

                  </Link>

                </template>

              </div>

            </div>

          </transition>

        </div>

      </div>

    </nav>
  </aside>
      <!-- モバイルオーバーレイ -->
  <div
    v-if="mobileOpen"
    class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
    @click="mobileOpen = false"
  ></div>
  </div>
  <style>
    .slide-fade-enter-active, .slide-fade-leave-active { transition: all 0.2s ease; }
    .slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; max-height: 0; }
    .slide-fade-enter-to, .slide-fade-leave-from { opacity: 1; max-height: 500px; }
  </style>
</template>



