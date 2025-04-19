const app = new Vue({
  el: '.main-content',
  data: {
    showEditor: false,
    editingTemplate: null,
    editorInstance: null
  },
  methods: {
    showCreateTemplate() {
      this.editingTemplate = null;
      this.showEditor = true;
      this.$nextTick(() => {
        this.editorInstance = new Quill('#contractEditor', {
          modules: {
            toolbar: [
              [{ header: [1, 2, false] }],
              ['bold', 'italic', 'underline'],
              ['code-block'],
              [{ list: 'ordered' }, { list: 'bullet' }],
              ['link', 'image']
            ]
          },
          theme: 'snow'
        });
      });
    },
    closeEditor() {
      this.showEditor = false;
      this.editorInstance = null;
    },
    async saveTemplate() {
      const content = this.editorInstance.root.innerHTML;
      try {
        const analyzedContent = await axios.post('/api/analyze-clause', {
          content,
          apiKey: DEEPSEEK_API_KEY
        });

        if(analyzedContent.data.riskLevel > 2) {
          throw new Error('条款风险等级过高：' + analyzedContent.data.riskReasons.join(','));
        }

        const response = await axios.post('/api/templates', {
          content: analyzedContent.data.safeContent,
          ...this.editingTemplate,
          auditTrail: analyzedContent.data.auditInfo
        });
        // 更新模板列表并记录区块链存证
        this.closeEditor();
      } catch (error) {
        console.error('保存失败:', error);
      }
    }
  }
});