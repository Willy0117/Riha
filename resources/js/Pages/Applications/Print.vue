<template>
<div>

    <!-- 背景未選択 -->
    <div v-if="!application.background">
        <h3>背景選択</h3>

        <select v-model="selectedBackground">
            <option value="green">green</option>
            <option value="blue">blue</option>
            <option value="orange">orange</option>
            <option value="pink">pink</option>
        </select>

        <button @click="setBackground">決定</button>
    </div>


    <!-- 印刷画面 -->
    <div v-if="application.background" id="print-area">

        <div class="sheet">

            <img class="bg" :src="bgPath">
            <img class="drawing" :src="drawingPath">

        </div>

        <button class="no-print" @click="print">印刷</button>

    </div>

</div>
</template>


<script>
export default {

    props: {
        application: Object
    },

    data() {
        return {
            selectedBackground: null
        }
    },

    computed: {

        bgPath() {
            return "/storage/templates/bg/" + this.application.background + ".png"
        },

        drawingPath() {
            return "/storage/canvas/" + this.application.canvas_file
        }

    },

    methods: {

        setBackground() {

            this.$inertia.post(
                "/applications/background/" + this.application.id,
                { background: this.selectedBackground }
            )

        },

        print() {
            window.print()
        }

    }

}
</script>


<style scoped>

.sheet {
    position: relative;
    width: 1051px;
}

.bg {
    width: 100%;
}

.drawing {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
}

@media print {

    .no-print {
        display: none;
    }

}

</style>