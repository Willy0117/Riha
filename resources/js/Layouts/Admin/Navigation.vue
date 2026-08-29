<template>
  <div class="flex">
    <!-- モバイル用ハンバーガー -->
    <button
      @click="mobileOpen = !mobileOpen"
      class="lg:hidden p-2 rounded-full hover:bg-gray-200"
    >
      <template v-if="mobileOpen">
        <X class="w-5 h-5 text-gray-600" />
      </template>
      <template v-else>
        <Menu class="w-5 h-5 text-gray-600" />
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
    <!-- 開いている時 -->
    <div v-if="!collapsed" class="flex items-center justify-between p-2 border-b border-gray-200">
      <div class="flex items-center gap-2">
        <div class="logo-icon">JS</div>
        <div class="overflow-hidden">
          <span class="block text-xs font-bold text-gray-800 leading-tight truncate">
            指導士資格更新システム
          </span>
          <span class="block text-gray-400 tracking-widest truncate" style="font-size:9px">
            RENEWAL MANAGEMENT SYSTEM
          </span>
        </div>
      </div>
      <button @click="toggleCollapse" class="p-2 rounded-full hover:bg-gray-200 flex-none">
        <XMarkIcon class="w-5 h-5 text-gray-600" />
      </button>
    </div>

    <!-- 閉じている時 -->
    <div v-else class="flex flex-col items-center py-2 border-b border-gray-200 gap-1">
      <div class="logo-icon">JS</div>
      <button @click="toggleCollapse" class="p-2 rounded-full hover:bg-gray-200">
        <Menu class="w-5 h-5 text-gray-600" />
      </button>
    </div>

    <!-- メニュー -->
    <nav class="flex-1 overflow-y-auto px-2 py-4 text-sm">
      <!-- Dashboard -->
      <Link
        :href="route('admin.dashboard')"
        class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        :class="isActive('dashboard') ? 'bg-gray-300 font-semibold' : ''"
      >
        <Home class="w-5 h-5"/>
        <span v-if="!collapsed" class="ml-2">{{ t('dashboard') }}</span>
      </Link>
      <div v-if="can('members.view')" class="mt-2">
        <button
          @click="toggleSubMenu('members')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Users class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('member') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'members' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="slide-fade">
          <div v-show="openSubMenu === 'members' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.members.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
              :class="isActive('admin.members.index') ? 'bg-gray-200 font-semibold' : ''"
            >
              <Users class="w-4 h-4 mr-1"/>
              {{ t('member') }}
            </Link>
          </div>
        </transition>
      </div> 

<!-- 審査員ポータル（新規：担当申請の書類確認・承認/差し戻し） -->
      <div v-if="can('reviewers.view') || can('reviewers.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('reviewers')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <CheckCircle2 class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">審査員ポータル</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'reviewers' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'reviewers' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.reviewer.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('reviewer.index') ? 'bg-gray-300 font-semibold' : ''"
            >
              <CheckCircle2 class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">担当申請の審査</span>
            </Link>
          </div>
        </transition>
      </div>

      <!-- 審査委員長ポータル（新規：最終判定） -->
      <div v-if="can('chiefs.view') || can('chiefs.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('chief')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Award class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">審査委員長ポータル</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'chief' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'chief' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.chief.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('chief.index') ? 'bg-gray-300 font-semibold' : ''"
            >
              <Award class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">更新申請の最終判定</span>
            </Link>
          </div>
        </transition>
      </div>

      <!-- アサイン担当者ポータル（新規：審査員アサイン） -->
      <div v-if="can('subleaders.view')" class="mt-2">
        <button
          @click="toggleSubMenu('subleader')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <GraduationCap class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">アサイン担当者ポータル</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'subleader' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'subleader' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.subleader.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('subleader.index') ? 'bg-gray-300 font-semibold' : ''"
            >
              <GraduationCap class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">審査員アサイン</span>
            </Link>
          </div>
        </transition>
      </div>       
     <!-- 事務局ポータル（なんでもあり） -->
      <div v-if="can('instructorMembers.view')" class="mt-2">
        <button
          @click="toggleSubMenu('instructorMember')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <GraduationCap class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">事務局ポータル</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'instructorMember' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'instructorMember' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.instructorMembers.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('instructorMembers.index') ? 'bg-gray-300 font-semibold' : ''"
            >
              <GraduationCap class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">事務局</span>
            </Link>
            <Link
              :href="route('admin.schedules.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
              :class="isActive('schedules.index') ? 'bg-gray-300 font-semibold' : ''"
            >
              <GraduationCap class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">スケジュールリマインダー設定</span>
            </Link>
            <Link v-if="can('imports.view')|| can('imports.edit')" :href="route('admin.import.index')"
                  class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
                  :class="isActive('admin.import.index') ? 'bg-gray-300 font-semibold' : ''">
              <Upload class="w-4 h-4 mr-1"/>
              <span v-if="!collapsed" class="ml-2">{{ t('import') }}</span>
            </Link>

          </div>
        </transition>
      </div>    
      <!-- Membes サブメニュー -->
     
      <!-- Organizations サブメニュー -->
      <div v-if="can('organizations.view') || can('organizations.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('organizations')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Building2 class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('organization') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'organizations' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="slide-fade">
          <div v-show="openSubMenu === 'organizations' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.organizations.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
              :class="isActive('admin.organizations.index') ? 'bg-gray-200 font-semibold' : ''"
            >
              <Building2 class="w-4 h-4 mr-1"/>
              {{ t('organization') }}
            </Link>
          </div>
        </transition>
      </div>

      <div v-if="can('invoices.view') || can('invoices.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('invoices')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Calendar class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('invoices') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'invoices' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'invoices' && !collapsed" class="pl-6 mt-1 space-y-1">
          </div>
        </transition>
      </div>

      <div v-if="can('credits.view') || can('credits.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('credits')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Settings class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">単位設定</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'credits' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'credits' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link :href="route('admin.credit-role-points.index')"
                  class="flex items-center py-2 px-2 rounded hover:bg-gray-200 transition-colors"
                  :class="isActive('admin.credit-role-points') ? 'bg-gray-300 font-semibold' : ''">
              <Calendar class="w-5 h-5"/>
              <span v-if="!collapsed" class="ml-2">単位設定</span>
            </Link>
          </div>
        </transition>
      </div>          
                  
      <div v-if="can('imports.view')|| can('imports.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('imports')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Upload class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('import') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'imports' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <transition name="slide-fade">
          <div v-show="openSubMenu === 'imports' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.import.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
            >
              <Upload class="w-4 h-4 mr-1"/>
              {{ t('import') }}
            </Link>
          </div>
        </transition>
      </div>  

                  <!-- Access Control -->
      <div v-if="can('tenants.view') || can('tenants.edit') || can('roles.view') || can('roles.edit') || can('permissions.view') || can('permissions.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('system')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Settings class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('access_control') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'system' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="slide-fade">
          <div v-show="openSubMenu === 'system' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              v-if="can('tenants.view') || can('tenants.edit')"
              :href="route('admin.tenants.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
            >
              <Building2 class="w-4 h-4 mr-1"/>
              {{ t('navigations.tenants') }}
            </Link>
            <Link
              v-if="can('roles.view') || can('roles.edit')"
              :href="route('admin.roles.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
            >
              <ShieldCheck class="w-4 h-4 mr-1"/>
              {{ t('navigations.roles') }}
            </Link>
            <Link
              v-if="can('permissions.view') || can('permissions.edit')"
              :href="route('admin.permissions.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
            >
              <Key class="w-4 h-4 mr-1"/>
              {{ t('navigations.permissions') }}
            </Link>
          </div>
        </transition>
      </div>      
      <!-- admin サブメニュー -->
      <div v-if="can('admins.view') || can('admins.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('admin')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Users class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('admin') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'admin' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="slide-fade">
          <div v-show="openSubMenu === 'admin' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.admins.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
              :class="isActive('admin.admins.index') ? 'bg-gray-200 font-semibold' : ''"
            >
              <Users class="w-4 h-4 mr-1"/>
              {{ t('admin') }}
            </Link>
          </div>
        </transition>
      </div>
      <!-- user サブメニュー -->
      <div v-if="can('users.view') || can('users.edit')" class="mt-2">
        <button
          @click="toggleSubMenu('users')"
          class="flex items-center justify-between w-full py-2 px-2 rounded hover:bg-gray-200 transition-colors"
        >
          <div class="flex items-center">
            <Users class="w-5 h-5"/>
            <span v-if="!collapsed" class="ml-2">{{ t('user') }}</span>
          </div>
          <svg
            v-if="!collapsed"
            :class="{ 'rotate-90': openSubMenu === 'users' }"
            class="w-4 h-4 transform transition-transform duration-200"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="slide-fade">
          <div v-show="openSubMenu === 'users' && !collapsed" class="pl-6 mt-1 space-y-1">
            <Link
              :href="route('admin.users.index')"
              class="flex items-center py-2 px-2 rounded hover:bg-gray-100"
              :class="isActive('admin.users.index') ? 'bg-gray-200 font-semibold' : ''"
            >
              <User class="w-4 h-4 mr-1"/>
              {{ t('user') }}
            </Link>
          </div>
        </transition>
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
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import {
  Home, Users, User, ShieldCheck, Award, 
  Building2, Menu,
  X,Key, GraduationCap,
  FileCheck,
  RefreshCw,
  FileText,
  Calendar,Upload,
  CheckCircle2,
  ArrowLeft,Settings
} from 'lucide-vue-next'

const page = usePage()

const mobileOpen = ref(false)       // モバイル用の開閉状態

const collapsed = ref(false)
const openSubMenu = ref(null)

const toggleCollapse = () => (collapsed.value = !collapsed.value)
//const toggleSubMenu = (menu) => (openSubMenu.value = openSubMenu.value === menu ? null : menu)

const { props } = usePage()

const roles = props.auth?.admin?.roles ?? []
const permissions = props.auth?.admin?.permissions ?? []

const hasRole = (role) => roles.includes(role)

const can = (permission) => permissions.includes(permission)

const user = props.auth.admin ?? props.auth.user

// i18n
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const isActive = (routeName) => false
const hasApiFeatures = true

const validMenus = ['members', 'admins', 'users', 'systems','annual_fees','organizations', 'approvals', 'renewals',
 'certifications','imports', 'invoices', 'credits', 'instructorMember' ]

// 自動判定
const detectMenu = () => {
  const current = route().current()
  if (!current) return null

  const parts = current.split('.')
  if (parts.length < 2) return null

  const key = parts[1]

  if (validMenus.includes(key)) {
    return key
  }

  return null
}

// 初期化
onMounted(() => {
  // localStorage優先
  const saved = localStorage.getItem('openMenu')

  if (saved) {
    openSubMenu.value = saved
  } else {
    openSubMenu.value = detectMenu()
  }
})

// 状態保存
watch(openSubMenu, (val) => {
  if (val) {
    localStorage.setItem('openMenu', val)
  }
})

// トグル
const toggleSubMenu = (menu) => {
  openSubMenu.value = openSubMenu.value === menu ? null : menu
}
/*
onMounted(() => {
  if (page.url.startsWith('/admin/member') ) {
    openSubMenu.value = 'member'
  }
  if (page.url.startsWith('/admin/tenants') ||
      page.url.startsWith('/admin/roles') ||
      page.url.startsWith('/admin/permissions')) {
    openSubMenu.value = 'access'
  }
  if (page.url.startsWith('/admin/users') ) {
    openSubMenu.value = 'users'
  }
})
*/

</script>

<style>
.logo-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #2563eb;
  color: #fff;
  font-weight: 800;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.2s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  max-height: 0;
}
.slide-fade-enter-to,
.slide-fade-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style>