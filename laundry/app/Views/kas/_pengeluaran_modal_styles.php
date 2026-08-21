<style>
  .kas-pg-modal-dialog {
    width: calc(100% - 1.5rem);
    max-width: 760px;
    margin-left: auto;
    margin-right: auto;
  }

  .kas-pg-modal-grid {
    display: grid;
    gap: 0.75rem 1rem;
    grid-template-columns: 1fr;
  }

  @media (min-width: 576px) {
    .kas-pg-modal-grid {
      grid-template-columns: 1fr 1fr;
    }

    .kas-pg-modal-grid>.kas-pg-span-2 {
      grid-column: 1 / -1;
    }
  }

  .kas-pg-modal-grid .form-group,
  .kas-pg-modal-grid .mb-3 {
    margin-bottom: 0 !important;
  }

  .kas-pg-modal-grid .pg-ket-wrap {
    margin-bottom: 0;
  }
</style>
