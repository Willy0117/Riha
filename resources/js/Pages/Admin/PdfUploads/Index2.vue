<template>
  <div class="app-wrapper">
    <!-- ヘッダー -->
    <header class="header">
      <div class="header-left">
        <div class="logo-icon">JS</div>
        <div class="logo-text">
          <span class="logo-title">指導士資格更新システム</span>
          <span class="logo-sub">RENEWAL MANAGEMENT SYSTEM</span>
        </div>
      </div>
      <nav class="header-nav">
        <button
          v-for="role in roles"
          :key="role.key"
          :class="['nav-btn', { active: currentRole === role.key }]"
          @click="currentRole = role.key"
        >
          {{ role.label }}
        </button>
      </nav>
      <div class="header-user">
        <div class="user-info">
          <span class="user-name">事務局管理者</span>
          <span class="user-role">SYSTEM ADMIN</span>
        </div>
        <div class="user-avatar">
          <img src="https://i.pravatar.cc/40?img=3" alt="avatar" />
        </div>
      </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main">
      <!-- ページヘッダー -->
      <div class="page-header">
        <div class="page-header-left">
          <h1 class="page-title">事務局管理パネル</h1>
          <p class="page-desc">会員管理、入金状況の同期、およびSMOOSYデータのインポートを行います。</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-primary" @click="handleImport">
            <span class="btn-icon">↑</span>
            会員情報インポート (SMOOSY)
          </button>
          <button class="btn btn-outline" @click="handlePaymentSync">
            <span class="btn-icon">↑</span>
            入金データ同期 (SMOOSY)
          </button>
          <button class="btn btn-outline" @click="handleExport">
            <span class="btn-icon">↓</span>
            審査完了者リスト出力 (SMOOSY)
          </button>
        </div>
      </div>

      <!-- タブ -->
      <div class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="['tab-btn', { active: currentTab === tab.key }]"
          @click="currentTab = tab.key"
        >
          <span class="tab-icon">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- テーブルカード -->
      <div class="table-card">
        <div class="table-header">
          <div class="table-header-left">
            <h2 class="table-title">申請者一覧</h2>
            <p class="table-desc">納入状況の更新と更新対象者の設定</p>
          </div>
          <div class="table-controls">
            <button
              class="btn btn-danger-outline"
              :disabled="selectedIds.length === 0"
              @click="handleDeleteSelected"
            >
              選択した申請者を削除 ({{ selectedIds.length }}名)
            </button>
            <div class="filter-select-wrapper">
              <span class="filter-icon">▼</span>
              <select v-model="filterYear" class="filter-select">
                <option value="">更新予定年（すべて）</option>
                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}年</option>
              </select>
            </div>
            <div class="search-wrapper">
              <span class="search-icon">🔍</span>
              <input
                v-model="searchQuery"
                type="text"
                class="search-input"
                placeholder="名前・メール・個人番号で検索..."
              />
            </div>
          </div>
        </div>

        <!-- テーブル -->
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-check">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                  />
                </th>
                <th>会員番号</th>
                <th>氏名</th>
                <th>取得年</th>
                <th>更新予定年</th>
                <th>年会費</th>
                <th>更新料</th>
                <th>現在の単位</th>
                <th>本申請ステータス</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredMembers.length === 0">
                <td colspan="10" class="empty-row">
                  <div class="empty-state">
                    <span class="empty-icon">📋</span>
                    <p>表示するデータがありません</p>
                  </div>
                </td>
              </tr>
              <tr
                v-for="member in filteredMembers"
                :key="member.id"
                :class="{ selected: selectedIds.includes(member.id) }"
              >
                <td class="col-check">
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(member.id)"
                    @change="toggleSelect(member.id)"
                  />
                </td>
                <td>{{ member.memberNo }}</td>
                <td>{{ member.name }}</td>
                <td>{{ member.acquiredYear }}</td>
                <td>{{ member.renewalYear }}</td>
                <td>{{ member.annualFee.toLocaleString() }}円</td>
                <td>{{ member.renewalFee.toLocaleString() }}円</td>
                <td>{{ member.currentUnit }}</td>
                <td>
                  <span :class="['status-badge', statusClass(member.status)]">
                    {{ member.status }}
                  </span>
                </td>
                <td>
                  <button class="action-btn" @click="handleEdit(member)">編集</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <!-- フッター -->
    <footer class="footer">
      <p class="footer-copy">© 2026 日本腎臓リハビリテーション学会. All rights reserved.</p>
      <nav class="footer-nav">
        <a href="#">利用規約</a>
        <a href="#">プライバシーポリシー</a>
        <a href="#">お問い合わせ</a>
      </nav>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// --- ロール切替 ---
const roles = [
  { key: 'office', label: '事務局' },
  { key: 'examiner', label: '審査員' },
  { key: 'applicant', label: '申請者' },
]
const currentRole = ref('office')

// --- タブ ---
const tabs = [
  { key: 'applicants', label: '申請者・納入管理', icon: '👤' },
  { key: 'settings', label: 'システム設定', icon: '⚙️' },
]
const currentTab = ref('applicants')

// --- 検索・フィルター ---
const searchQuery = ref('')
const filterYear = ref('')
const yearOptions = [2024, 2025, 2026, 2027]

// --- 選択 ---
const selectedIds = ref([])

// --- サンプルデータ ---
const members = ref([
  {
    id: 1,
    memberNo: 'M-00123',
    name: '山田 太郎',
    acquiredYear: 2020,
    renewalYear: 2025,
    annualFee: 10000,
    renewalFee: 5000,
    currentUnit: 30,
    status: '申請済み',
  },
  {
    id: 2,
    memberNo: 'M-00456',
    name: '佐藤 花子',
    acquiredYear: 2019,
    renewalYear: 2024,
    annualFee: 10000,
    renewalFee: 5000,
    currentUnit: 45,
    status: '審査中',
  },
  {
    id: 3,
    memberNo: 'M-00789',
    name: '鈴木 一郎',
    acquiredYear: 2021,
    renewalYear: 2026,
    annualFee: 10000,
    renewalFee: 5000,
    currentUnit: 20,
    status: '未申請',
  },
])

// --- フィルタリング ---
const filteredMembers = computed(() => {
  return members.value.filter((m) => {
    const matchYear = filterYear.value === '' || m.renewalYear === Number(filterYear.value)
    const q = searchQuery.value.toLowerCase()
    const matchSearch =
      q === '' ||
      m.name.includes(q) ||
      m.memberNo.toLowerCase().includes(q)
    return matchYear && matchSearch
  })
})

// --- 全選択 ---
const isAllSelected = computed(
  () =>
    filteredMembers.value.length > 0 &&
    filteredMembers.value.every((m) => selectedIds.value.includes(m.id))
)

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = filteredMembers.value.map((m) => m.id)
  }
}

const toggleSelect = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((i) => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

// --- ステータスクラス ---
const statusClass = (status) => {
  const map = {
    申請済み: 'status-applied',
    審査中: 'status-reviewing',
    承認済み: 'status-approved',
    未申請: 'status-pending',
    否認: 'status-rejected',
  }
  return map[status] ?? ''
}

// --- アクション ---
const handleImport = () => alert('会員情報インポート (SMOOSY)')
const handlePaymentSync = () => alert('入金データ同期 (SMOOSY)')
const handleExport = () => alert('審査完了者リスト出力 (SMOOSY)')
const handleDeleteSelected = () => {
  if (confirm(`選択した ${selectedIds.value.length} 名を削除しますか？`)) {
    members.value = members.value.filter((m) => !selectedIds.value.includes(m.id))
    selectedIds.value = []
  }
}
const handleEdit = (member) => alert(`編集: ${member.name}`)
</script>

<style scoped>
/* ===== リセット・基本 ===== */
* { box-sizing: border-box; margin: 0; padding: 0; }

.app-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f4f5f7;
  font-family: 'Hiragino Sans', 'Noto Sans JP', sans-serif;
  color: #1a1a2e;
}

/* ===== ヘッダー ===== */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 32px;
  height: 64px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #2563eb;
  color: #fff;
  font-weight: 800;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-title {
  display: block;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.logo-sub {
  display: block;
  font-size: 10px;
  color: #6b7280;
  letter-spacing: 0.08em;
}

.header-nav {
  display: flex;
  gap: 4px;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 4px;
}

.nav-btn {
  padding: 6px 18px;
  border: none;
  border-radius: 6px;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}

.nav-btn.active {
  background: #fff;
  color: #111827;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}

.header-user {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-name {
  display: block;
  font-size: 13px;
  font-weight: 600;
  text-align: right;
}

.user-role {
  display: block;
  font-size: 10px;
  color: #9ca3af;
  text-align: right;
}

.user-avatar img {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

/* ===== メイン ===== */
.main {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 40px 32px 32px;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* ===== ページヘッダー ===== */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #1e3a6e;
  font-style: italic;
  letter-spacing: -0.5px;
}

.page-desc {
  margin-top: 6px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.6;
}

.page-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* ===== ボタン ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}

.btn-icon {
  font-size: 14px;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-outline {
  background: #fff;
  color: #2563eb;
  border-color: #2563eb;
}

.btn-outline:hover {
  background: #eff6ff;
}

.btn-danger-outline {
  background: #fff;
  color: #dc2626;
  border-color: #fca5a5;
  font-size: 12px;
  padding: 7px 14px;
}

.btn-danger-outline:disabled {
  color: #9ca3af;
  border-color: #e5e7eb;
  cursor: not-allowed;
}

/* ===== タブ ===== */
.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e5e7eb;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s;
}

.tab-btn.active {
  color: #2563eb;
  border-bottom-color: #2563eb;
  font-weight: 600;
}

/* ===== テーブルカード ===== */
.table-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  gap: 16px;
  flex-wrap: wrap;
  border-bottom: 1px solid #f3f4f6;
}

.table-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.table-desc {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.table-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.filter-icon {
  position: absolute;
  left: 10px;
  font-size: 10px;
  color: #6b7280;
  pointer-events: none;
}

.filter-select {
  padding: 7px 12px 7px 26px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  cursor: pointer;
  appearance: none;
}

.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  font-size: 13px;
  pointer-events: none;
}

.search-input {
  padding: 7px 12px 7px 32px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  width: 240px;
  outline: none;
  transition: border-color 0.15s;
}

.search-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* ===== テーブル ===== */
.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  white-space: nowrap;
}

.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
  white-space: nowrap;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr.selected td {
  background: #eff6ff;
}

.data-table tbody tr:hover td {
  background: #f9fafb;
}

.col-check {
  width: 44px;
  text-align: center;
}

.empty-row {
  padding: 60px 16px !important;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
}

.empty-icon {
  font-size: 32px;
  opacity: 0.4;
}

/* ===== ステータスバッジ ===== */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-applied   { background: #dbeafe; color: #1d4ed8; }
.status-reviewing { background: #fef3c7; color: #d97706; }
.status-approved  { background: #d1fae5; color: #065f46; }
.status-pending   { background: #f3f4f6; color: #6b7280; }
.status-rejected  { background: #fee2e2; color: #dc2626; }

/* ===== 操作ボタン ===== */
.action-btn {
  padding: 5px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}

.action-btn:hover {
  border-color: #2563eb;
  color: #2563eb;
}

/* ===== フッター ===== */
.footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 32px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
  font-size: 12px;
  color: #9ca3af;
}

.footer-nav {
  display: flex;
  gap: 20px;
}

.footer-nav a {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.15s;
}

.footer-nav a:hover {
  color: #2563eb;
}
</style>